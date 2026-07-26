<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

namespace block_curriculummap\external;

use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_single_structure;
use core_external\external_multiple_structure;
use core_external\external_value;
use context_system;
use core_competency\api as competency_api;
use core_competency\competency_framework;

/**
 * Build the "common format" intermediate data model (subjects[] + axis
 * registry) described in the design memo:
 *
 *   [data source layer]                          [intermediate model]         [viz layer]
 *   course.idnumber substring (category)       ──┐
 *   core_competency OR CSV (dp/core/milestone) ──┤─→ subjects[] + axes{} ──→ (AMD renders)
 *
 * Design decisions from chat (see conversation notes, not just this file):
 * - "category" comes straight from a configurable substring of
 *   course.idnumber (e.g. "2026_M_L3XXXX" chars 8-9 -> "L3"). No framework,
 *   no CSV, no custom field - just string slicing, per-institution offset/length
 *   configured by a Manager in manage.php.
 * - "milestone" is its own axis, parallel to dp/core (not nested inside the
 *   DP hierarchy) - see chat notes on why nesting would model a case that
 *   can't occur under the current rule (one milestone value per subject).
 * - Each of dp/core/milestone independently supports TWO datasources,
 *   toggled per-axis by a Manager in manage.php: 'competency' (live from
 *   core_competency) or 'csv' (block_curriculummap_link, populated by
 *   csv_import.php).
 * - CSV-sourced subjects do NOT need a matching Moodle course to exist.
 *   Subject identity is the course_idnumber string itself; if it happens to
 *   match a real course, that course's real name/id are used, otherwise the
 *   CSV's optional course_name (or the idnumber itself) is used as a
 *   "virtual" subject. This is why subjects are keyed by a canonical string
 *   key here rather than Moodle's numeric courseid - it lets a
 *   competency-sourced axis and a CSV-sourced axis merge into the same
 *   subject when they share an idnumber, while still letting CSV describe
 *   subjects Moodle doesn't know about yet.
 *
 * @package    block_curriculummap
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class get_data extends external_api {

    /** Axis ids this plugin currently knows about. */
    const AXES = ['dp', 'core', 'milestone'];

    /**
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([]);
    }

    /**
     * @return array
     */
    public static function execute(): array {
        $context = context_system::instance();
        self::validate_context($context);
        require_capability('block/curriculummap:view', $context);

        $axes = [];
        $subjectsbykey = [];

        foreach (self::AXES as $axisid) {
            $axisdata = self::get_axis_data($axisid);
            if ($axisdata === null) {
                continue; // Not configured (neither framework nor CSV data present) - skip gracefully.
            }

            $axes[] = [
                'id'    => $axisid,
                'label' => $axisdata['label'],
                'items' => $axisdata['items'],
            ];

            foreach ($axisdata['courselinks'] as $link) {
                $key = self::get_or_create_subject($subjectsbykey, $link['courseid'], $link['courseidnumber'],
                    $link['coursename'] ?? null);
                $subjectsbykey[$key]['links'][] = [
                    'axisid'    => $axisid,
                    'idnumbers' => $link['itemidnumbers'],
                ];
            }
        }

        return [
            'axes'     => $axes,
            'subjects' => array_values($subjectsbykey),
        ];
    }

    /**
     * Finds (or creates) the subject entry for a course, keyed canonically
     * so the same real-world subject merges across axes/datasources even
     * when one axis only knows it as a CSV idnumber and another knows it as
     * a real Moodle course.
     *
     * @param array $subjectsbykey Passed by reference; mutated in place.
     * @param int|null $courseid Real Moodle course id, or null if unmatched (CSV-only/virtual).
     * @param string $courseidnumber Canonical identity when non-empty.
     * @param string|null $fallbackname Used for virtual subjects when no course_name was given either.
     * @return string The key used in $subjectsbykey for this subject.
     */
    private static function get_or_create_subject(array &$subjectsbykey, ?int $courseid,
            string $courseidnumber, ?string $fallbackname): string {

        $key = $courseidnumber !== '' ? $courseidnumber : ('C' . $courseid);

        if (!isset($subjectsbykey[$key])) {
            $subjectsbykey[$key] = self::build_subject($key, $courseid, $courseidnumber, $fallbackname);
        } else if ($courseid !== null && $subjectsbykey[$key]['courseid'] === null) {
            // A later axis resolved a real course for a subject we'd only seen virtually so far - upgrade it.
            $subjectsbykey[$key] = array_merge(
                self::build_subject($key, $courseid, $courseidnumber, $fallbackname),
                ['links' => $subjectsbykey[$key]['links']]
            );
        }

        return $key;
    }

    /**
     * @param string $key
     * @param int|null $courseid
     * @param string $courseidnumber
     * @param string|null $fallbackname
     * @return array
     */
    private static function build_subject(string $key, ?int $courseid, string $courseidnumber, ?string $fallbackname): array {
        if ($courseid !== null) {
            $course = get_course($courseid);
            return [
                'id'       => 'S_' . $key,
                'courseid' => $courseid,
                'name'     => format_string($course->fullname),
                'category' => self::extract_category($course->idnumber),
                'links'    => [],
            ];
        }
        $name = ($fallbackname !== null && $fallbackname !== '') ? $fallbackname : $courseidnumber;
        return [
            'id'       => 'S_' . $key,
            'courseid' => null,
            'name'     => $name,
            'category' => self::extract_category($courseidnumber),
            'links'    => [],
        ];
    }

    /**
     * Category comes from a configurable substring of course.idnumber
     * (chat notes: e.g. "2026_M_L3XXXX", offset 7, length 2 -> "L3"). Works
     * the same whether idnumber comes from a real course or a CSV-only
     * virtual subject - it's pure string slicing either way.
     * Defaults (offset 7, length 2) match that example but are meant to be
     * reconfigured per institution in manage.php.
     *
     * @param string $idnumber
     * @return string|null null if idnumber is too short or unset.
     */
    private static function extract_category(string $idnumber): ?string {
        $offset = (int)(get_config('block_curriculummap', 'category_idnumber_offset') ?: 7);
        $length = (int)(get_config('block_curriculummap', 'category_idnumber_length') ?: 2);
        if ($idnumber === '' || strlen($idnumber) < $offset + $length) {
            return null;
        }
        return substr($idnumber, $offset, $length);
    }

    /**
     * Resolves one axis's data from whichever datasource a Manager has
     * configured for it (default 'competency').
     *
     * @param string $axisid 'dp' | 'core' | 'milestone'
     * @return array{label:string, items:array, courselinks:array}|null
     */
    private static function get_axis_data(string $axisid): ?array {
        $datasource = get_config('block_curriculummap', "{$axisid}_datasource");
        $datasource = $datasource ?: 'competency';

        if ($datasource === 'csv') {
            return self::get_axis_data_from_csv($axisid);
        }
        return self::get_axis_data_from_competency($axisid);
    }

    /**
     * @param string $axisid
     * @return array{label:string, items:array, courselinks:array}|null
     */
    private static function get_axis_data_from_competency(string $axisid): ?array {
        $idnumber = get_config('block_curriculummap', "{$axisid}frameworkidnumber");
        if (empty($idnumber)) {
            return null;
        }
        $framework = competency_framework::get_record(['idnumber' => $idnumber]);
        if (!$framework) {
            return null;
        }
        return [
            'label'       => $framework->get('shortname'),
            'items'       => self::build_hierarchy($framework),
            'courselinks' => self::get_course_links($framework),
        ];
    }

    /**
     * @param string $axisid
     * @return array{label:string, items:array, courselinks:array}|null
     */
    private static function get_axis_data_from_csv(string $axisid): ?array {
        global $DB;
        $rows = $DB->get_records('block_curriculummap_link', ['axisid' => $axisid]);
        if (empty($rows)) {
            return null;
        }

        $items = [];
        $seenitems = [];
        $bykey = []; // courseidnumber => courselink entry, so multiple item rows for one subject merge.

        foreach ($rows as $row) {
            if (!isset($seenitems[$row->itemidnumber])) {
                $items[] = [
                    'idnumber' => $row->itemidnumber,
                    'label'    => $row->itemlabel !== '' ? $row->itemlabel : $row->itemidnumber,
                    'group'    => $row->parentlabel,
                ];
                $seenitems[$row->itemidnumber] = true;
            }

            $key = $row->courseidnumber;
            if (!isset($bykey[$key])) {
                $bykey[$key] = [
                    'courseid'       => $row->courseid ? (int)$row->courseid : null,
                    'courseidnumber' => $row->courseidnumber,
                    'coursename'     => $row->coursename,
                    'itemidnumbers'  => [],
                ];
            }
            $bykey[$key]['itemidnumbers'][] = $row->itemidnumber;
        }

        return [
            'label'       => get_string("axisheading_{$axisid}", 'block_curriculummap'),
            'items'       => $items,
            'courselinks' => array_values($bykey),
        ];
    }

    /**
     * Flattens a competency framework into rows with a "group" label (the
     * parent competency's shortname, or '' for top-level items), matching
     * the prototype's major/minor banding - but keyed on a display string
     * rather than a numeric parent id, so this shape lines up with the CSV
     * datasource's output (see get_axis_data_from_csv()).
     *
     * @param competency_framework $framework
     * @return array
     */
    private static function build_hierarchy(competency_framework $framework): array {
        $competencies = competency_api::list_competencies([
            'competencyframeworkid' => $framework->get('id'),
        ]);

        $shortnamebyid = [];
        foreach ($competencies as $competency) {
            $shortnamebyid[$competency->get('id')] = $competency->get('shortname');
        }

        $items = [];
        foreach ($competencies as $competency) {
            $parentid = $competency->get('parentid');
            $items[] = [
                'idnumber' => $competency->get('idnumber'),
                'label'    => $competency->get('shortname'),
                'group'    => $parentid ? ($shortnamebyid[$parentid] ?? '') : '',
            ];
        }
        return $items;
    }

    /**
     * For a given framework, find which courses each of its competencies is
     * linked to via core_competency's standard course-competency link
     * (populated by the bulk-link tool described in the design memo).
     * Competency-sourced subjects always have a real course, so courseid is
     * never null here - but the key/courseidnumber fields are still filled
     * in for merge-compatibility with CSV-sourced axes on the same course.
     *
     * Note: \core_competency\api::list_course_competencies() returns an array
     * of plain associative arrays shaped like
     * ['competency' => \core_competency\competency, 'coursecompetency' => ...],
     * NOT objects with a get_competency() method - confirmed against Moodle
     * core source (competency/classes/api.php).
     *
     * TODO (performance): this iterates every course in the system. Fine for
     * a single institution's ~100-300 subject scale from the design memo, but
     * worth revisiting (batched SQL / caching) before wider distribution.
     *
     * @param competency_framework $framework
     * @return array List of courselink entries (see get_axis_data_from_csv() shape).
     */
    private static function get_course_links(competency_framework $framework): array {
        global $DB;
        $result = [];
        $courses = $DB->get_records('course', null, '', 'id, idnumber');
        foreach ($courses as $course) {
            if ($course->id == SITEID) {
                continue;
            }
            $linked = competency_api::list_course_competencies($course->id);
            $idnumbers = [];
            foreach ($linked as $item) {
                $competency = $item['competency'];
                if ($competency->get('competencyframeworkid') == $framework->get('id')) {
                    $idnumbers[] = $competency->get('idnumber');
                }
            }
            if (!empty($idnumbers)) {
                $result[] = [
                    'courseid'       => (int)$course->id,
                    'courseidnumber' => (string)$course->idnumber,
                    'coursename'     => null,
                    'itemidnumbers'  => $idnumbers,
                ];
            }
        }
        return $result;
    }

    /**
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'axes' => new external_multiple_structure(
                new external_single_structure([
                    'id'    => new external_value(PARAM_ALPHA, 'Axis id: dp / core / milestone'),
                    'label' => new external_value(PARAM_TEXT, 'Framework shortname, or a generic label for CSV-sourced axes'),
                    'items' => new external_multiple_structure(
                        new external_single_structure([
                            'idnumber' => new external_value(PARAM_RAW, 'Item idnumber'),
                            'label'    => new external_value(PARAM_TEXT, 'Item display label'),
                            'group'    => new external_value(PARAM_TEXT, 'Parent/major grouping label, empty string if none'),
                        ])
                    ),
                ])
            ),
            'subjects' => new external_multiple_structure(
                new external_single_structure([
                    'id'       => new external_value(PARAM_RAW, 'Subject id, synthetic string key'),
                    'courseid' => new external_value(PARAM_INT, 'Moodle course id, null for virtual (CSV-only) subjects',
                        VALUE_DEFAULT, null, NULL_ALLOWED),
                    'name'     => new external_value(PARAM_TEXT, 'Course full name, or CSV-provided/fallback name'),
                    'category' => new external_value(PARAM_RAW, 'Derived from idnumber substring', VALUE_DEFAULT, null, NULL_ALLOWED),
                    'links'    => new external_multiple_structure(
                        new external_single_structure([
                            'axisid'    => new external_value(PARAM_ALPHA, 'Axis id: dp / core / milestone'),
                            'idnumbers' => new external_multiple_structure(
                                new external_value(PARAM_RAW, 'Linked item idnumber')
                            ),
                        ]),
                        'Per-axis links for this subject',
                        VALUE_DEFAULT,
                        []
                    ),
                ])
            ),
        ]);
    }
}
