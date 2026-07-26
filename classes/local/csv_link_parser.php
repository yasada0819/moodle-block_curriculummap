<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

namespace block_curriculummap\local;

defined('MOODLE_INTERNAL') || die();

/**
 * Parses and validates the
 * course_idnumber,item_idnumber,item_label,parent_label[,course_name]
 * CSV format used by csv_import.php.
 *
 * Design note (chat): a course_idnumber not matching any real Moodle course
 * is NOT an error - CSV-sourced axes can represent subjects that don't exist
 * in Moodle at all yet. course_name (5th column, optional) supplies a
 * display name for that case; if omitted, the idnumber itself is shown.
 * The only real error is a missing required field (course_idnumber or
 * item_idnumber empty).
 *
 * @package    block_curriculummap
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class csv_link_parser {

    /** Row parsed, course_idnumber matched a real Moodle course. */
    const STATUS_MATCHED = 'matched';
    /** Row parsed, no matching Moodle course - will still be imported as a virtual subject. */
    const STATUS_VIRTUAL = 'virtual';
    /** course_idnumber or item_idnumber was empty - blocking error. */
    const STATUS_MISSING_FIELDS = 'missing_fields';

    /** Statuses that are importable (i.e. not a hard error). */
    const IMPORTABLE_STATUSES = [self::STATUS_MATCHED, self::STATUS_VIRTUAL];

    /**
     * @param string $content Raw CSV file content.
     * @return array List of row objects: line, course_idnumber, item_idnumber,
     *               item_label, parent_label, course_name, status, courseid (null unless matched).
     */
    public static function parse(string $content): array {
        global $DB;

        $lines = preg_split('/\r\n|\r|\n/', trim($content));
        $rows = [];
        $linenum = 0;

        // Cache course_idnumber -> courseid lookups; small scale (design memo:
        // ~100-300 subjects) so no need for anything fancier.
        $courseidcache = [];

        foreach ($lines as $line) {
            $linenum++;
            if (trim($line) === '') {
                continue;
            }
            $cols = str_getcsv($line);
            $cols = array_map('trim', $cols);

            // Skip an optional header row.
            if ($linenum === 1 && isset($cols[0]) && strtolower($cols[0]) === 'course_idnumber') {
                continue;
            }

            $courseidnumber = $cols[0] ?? '';
            $itemidnumber   = $cols[1] ?? '';
            $itemlabel      = $cols[2] ?? '';
            $parentlabel    = $cols[3] ?? '';
            $coursename     = $cols[4] ?? '';

            $row = (object)[
                'line'            => $linenum,
                'course_idnumber' => $courseidnumber,
                'item_idnumber'   => $itemidnumber,
                'item_label'      => $itemlabel,
                'parent_label'    => $parentlabel,
                'course_name'     => $coursename,
                'status'          => self::STATUS_MATCHED,
                'courseid'        => null,
            ];

            if ($courseidnumber === '' || $itemidnumber === '') {
                $row->status = self::STATUS_MISSING_FIELDS;
                $rows[] = $row;
                continue;
            }

            if (!array_key_exists($courseidnumber, $courseidcache)) {
                $courseidcache[$courseidnumber] = $DB->get_field('course', 'id', ['idnumber' => $courseidnumber]) ?: null;
            }
            $courseid = $courseidcache[$courseidnumber];

            if ($courseid === null) {
                $row->status = self::STATUS_VIRTUAL;
            } else {
                $row->courseid = (int)$courseid;
                $row->status = self::STATUS_MATCHED;
            }
            $rows[] = $row;
        }

        return $rows;
    }

    /**
     * @param array $rows Output of self::parse().
     * @return array{matched:int, virtual:int, missing_fields:int, total:int}
     */
    public static function summarize(array $rows): array {
        $summary = ['matched' => 0, 'virtual' => 0, 'missing_fields' => 0, 'total' => count($rows)];
        foreach ($rows as $row) {
            $summary[$row->status]++;
        }
        return $summary;
    }
}
