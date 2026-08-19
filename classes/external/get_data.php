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
 *   course.idnumber. No framework, no CSV, no custom field - just string
 *   slicing, per-institution offset/length configured in manage.php.
 * - "milestone" is its own axis, parallel to dp/core (not nested inside the
 *   DP hierarchy).
 * - Each of dp/core/milestone independently supports TWO datasources:
 *   'competency' (live from core_competency) or 'csv'
 *   (block_curriculummap_link, via csv_import.php).
 * - Every one of the above can be set SITE-WIDE (the default every block
 *   instance uses unless it overrides) or PER BLOCK INSTANCE (chat notes:
 *   "医学部マップ" and "看護学部マップ" pointing at different DP
 *   frameworks while sharing the site-wide milestone setting). An instance
 *   override is only "on" for an axis when its own {axis}_datasource config
 *   key is explicitly 'competency' or 'csv' - the value 'inherit' (or the
 *   key being absent, e.g. for instances created before this feature)
 *   falls through to the site-wide default. CSV data itself is scoped the
 *   same way via block_curriculummap_link.instanceid (null = site-wide
 *   shared rows, which is what every pre-existing row already is).
 * - CSV-sourced subjects do NOT need a matching Moodle course to exist.
 *   Subject identity is the course_idnumber string itself; if it happens to
 *   match a real course, that course's real name/id are used, otherwise the
 *   CSV's optional course_name (or the idnumber itself) is used as a
 *   "virtual" subject.
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
        return new external_function_parameters([
            'instanceid' => new external_value(PARAM_INT,
                'Block instance id, so its own overrides (if any) are used; 0 = no specific instance (site-wide defaults only).',
                VALUE_DEFAULT, 0),
        ]);
    }

    /**
     * @param int $instanceid
     * @return array
     */
    public static function execute(int $instanceid = 0): array {
        global $DB;

        $instanceconfig = null;
        if ($instanceid) {
            $instancerecord = $DB->get_record('block_instances',
                ['id' => $instanceid, 'blockname' => 'curriculummap'], '*', MUST_EXIST);
            $blockinstance = block_instance('curriculummap', $instancerecord);
            $context = $blockinstance->context;
            $instanceconfig = $blockinstance->config ?: new \stdClass();
        } else {
            $context = context_system::instance();
        }

        self::validate_context($context);
        require_capability('block/curriculummap:view', $context);

        $axes = [];
        $subjectsbykey = [];

        foreach (self::AXES as $axisid) {
            $axisdata = self::get_axis_data($axisid, $instanceid, $instanceconfig);
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
                    $link['coursename'] ?? null, $instanceid, $instanceconfig);
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
     * @param int $instanceid
     * @param \stdClass|null $instanceconfig
     * @return string The key used in $subjectsbykey for this subject.
     */
    private static function get_or_create_subject(array &$subjectsbykey, ?int $courseid, string $courseidnumber,
            ?string $fallbackname, int $instanceid, ?\stdClass $instanceconfig): string {

        $key = $courseidnumber !== '' ? $courseidnumber : ('C' . $courseid);

        if (!isset($subjectsbykey[$key])) {
            $subjectsbykey[$key] = self::build_subject($key, $courseid, $courseidnumber, $fallbackname,
                $instanceid, $instanceconfig);
        } else if ($courseid !== null && $subjectsbykey[$key]['courseid'] === null) {
            // A later axis resolved a real course for a subject we'd only seen virtually so far - upgrade it.
            $subjectsbykey[$key] = array_merge(
                self::build_subject($key, $courseid, $courseidnumber, $fallbackname, $instanceid, $instanceconfig),
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
     * @param int $instanceid
     * @param \stdClass|null $instanceconfig
     * @return array
     */
    private static function build_subject(string $key, ?int $courseid, string $courseidnumber, ?string $fallbackname,
            int $instanceid, ?\stdClass $instanceconfig): array {
        if ($courseid !== null) {
            $course = get_course($courseid);
            return [
                'id'       => 'S_' . $key,
                'courseid' => $courseid,
                'name'     => format_string($course->fullname),
                'category' => self::extract_category($course->idnumber, $instanceid, $instanceconfig),
                'grade'    => self::extract_grade($course->idnumber),
                'links'    => [],
            ];
        }
        $name = ($fallbackname !== null && $fallbackname !== '') ? $fallbackname : $courseidnumber;
        return [
            'id'       => 'S_' . $key,
            'courseid' => null,
            'name'     => $name,
            'category' => self::extract_category($courseidnumber, $instanceid, $instanceconfig),
            'grade'    => self::extract_grade($courseidnumber),
            'links'    => [],
        ];
    }

    /**
     * Category comes from a configurable substring of course.idnumber
     * (e.g. "2026_M_L3XXXX", offset 7, length 2 -> "L3"). Works the same
     * whether idnumber comes from a real course or a CSV-only virtual
     * subject - it's pure string slicing either way. An instance can
     * override offset/length; if it hasn't, the site-wide default is used.
     *
     * @param string $idnumber
     * @param int $instanceid
     * @param \stdClass|null $instanceconfig
     * @return string|null null if idnumber is too short or unset.
     */
    private static function extract_category(string $idnumber, int $instanceid, ?\stdClass $instanceconfig): ?string {
        $offset = null;
        $length = null;
        if ($instanceid && $instanceconfig !== null && isset($instanceconfig->category_idnumber_offset)) {
            $offset = (int)$instanceconfig->category_idnumber_offset;
            $length = (int)($instanceconfig->category_idnumber_length ?? 2);
        } else {
            $offset = (int)(get_config('block_curriculummap', 'category_idnumber_offset') ?: 7);
            $length = (int)(get_config('block_curriculummap', 'category_idnumber_length') ?: 2);
        }
        if ($idnumber === '' || strlen($idnumber) < $offset + $length) {
            return null;
        }
        return substr($idnumber, $offset, $length);
    }

    /**
     * Grade (school year) comes from course.idnumber, same data source as
     * category but a different extraction shape: idnumber is split on "_"
     * and the first token matching /^M\d+$/ is used, e.g.
     * "2026_M_L74_M4_08" -> "M4". Chosen over a fixed offset/length (like
     * extract_category()) because the preceding "L" segment's digit count
     * varies (L2103-1, L4201, L74, L7302, ...), which would shift a fixed
     * offset; searching by token prefix is robust to that drift as long as
     * the naming convention (an underscore-delimited "M" + digits token)
     * holds.
     *
     * Design note (chat): a single Moodle course's idnumber never encodes
     * two grades at once. Where a syllabus-level subject genuinely spans
     * two school years (e.g. 科目管理番号 L7302 taught across M4 and M5),
     * that is represented as two separate Moodle courses with distinct
     * idnumbers (..._M4 and ..._M5), each with its own single-valued grade
     * - by design decision, these show up as two separate rows/subjects in
     * the map rather than being merged. So this stays a single-value
     * (multi: false) axis, no array return needed.
     *
     * Currently idnumber-only (no CSV alternative), unlike dp/core/
     * milestone. If a CSV-sourced category/grade datasource is wanted
     * later, this would become an axis-shaped lookup analogous to
     * get_axis_data() with a {axis}_datasource of 'idnumber' vs 'csv',
     * reusing block_curriculummap_link the same way dp/core/milestone do -
     * not implemented yet, kept as plain extraction for now.
     *
     * @param string $idnumber
     * @return string|null null if no "M" + digits token is found.
     */
    private static function extract_grade(string $idnumber): ?string {
        if ($idnumber === '') {
            return null;
        }
        foreach (explode('_', $idnumber) as $token) {
            if (preg_match('/^M\d+$/', $token)) {
                return $token;
            }
        }
        return null;
    }

    /**
     * Resolves one axis's data, using a block instance's own override for
     * datasource/framework if it has explicitly set one (anything other
     * than 'inherit'/unset), else the site-wide default.
     *
     * @param string $axisid 'dp' | 'core' | 'milestone'
     * @param int $instanceid
     * @param \stdClass|null $instanceconfig
     * @return array{label:string, items:array, courselinks:array}|null
     */
    private static function get_axis_data(string $axisid, int $instanceid, ?\stdClass $instanceconfig): ?array {
        $instancedatasource = ($instanceid && $instanceconfig !== null)
            ? ($instanceconfig->{"{$axisid}_datasource"} ?? 'inherit')
            : 'inherit';

        if ($instancedatasource === 'competency') {
            $idnumber = $instanceconfig->{"{$axisid}frameworkidnumber"} ?? '';
            return self::get_axis_data_from_competency($axisid, $idnumber);
        }
        if ($instancedatasource === 'csv') {
            return self::get_axis_data_from_csv($axisid, $instanceid);
        }

        // Inherit: fall through to the site-wide default.
        $sitedatasource = get_config('block_curriculummap', "{$axisid}_datasource") ?: 'competency';
        if ($sitedatasource === 'csv') {
            return self::get_axis_data_from_csv($axisid, 0);
        }
        $idnumber = get_config('block_curriculummap', "{$axisid}frameworkidnumber") ?: '';
        return self::get_axis_data_from_competency($axisid, $idnumber);
    }

    /**
     * @param string $axisid
     * @param string $idnumber Framework idnumber to use (already resolved: instance override or site default).
     * @return array{label:string, items:array, courselinks:array}|null
     */
    private static function get_axis_data_from_competency(string $axisid, string $idnumber): ?array {
        if ($idnumber === '') {
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
     * @param int $instanceid Which CSV scope to read: 0 = site-wide shared rows (instanceid IS NULL),
     *                        otherwise only that instance's own rows.
     * @return array{label:string, items:array, courselinks:array}|null
     */
    private static function get_axis_data_from_csv(string $axisid, int $instanceid): ?array {
        global $DB;
        $conditions = ['axisid' => $axisid, 'instanceid' => $instanceid ?: null];
        $rows = $DB->get_records('block_curriculummap_link', $conditions);
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
                    'grade'    => new external_value(PARAM_RAW, 'Derived from idnumber "_M<digits>" token', VALUE_DEFAULT, null, NULL_ALLOWED),
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
