<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

/**
 * Upgrade steps for block_curriculummap.
 *
 * @package    block_curriculummap
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * @param int $oldversion
 * @return bool
 */
function xmldb_block_curriculummap_upgrade($oldversion) {
    global $DB;
    $dbman = $DB->get_manager();

    if ($oldversion < 2026072606) {
        $table = new xmldb_table('block_curriculummap_link');

        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE);
        $table->add_field('axisid', XMLDB_TYPE_CHAR, '32', null, XMLDB_NOTNULL);
        $table->add_field('courseid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL);
        $table->add_field('itemidnumber', XMLDB_TYPE_CHAR, '255', null, XMLDB_NOTNULL);
        $table->add_field('itemlabel', XMLDB_TYPE_CHAR, '255', null, null);
        $table->add_field('parentlabel', XMLDB_TYPE_CHAR, '255', null, null);
        $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL);

        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
        $table->add_index('axisid', XMLDB_INDEX_NOTUNIQUE, ['axisid']);
        $table->add_index('courseid', XMLDB_INDEX_NOTUNIQUE, ['courseid']);

        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        upgrade_block_savepoint(true, 2026072606, 'curriculummap');
    }

    if ($oldversion < 2026072608) {
        $table = new xmldb_table('block_curriculummap_link');

        // CSV-sourced subjects no longer need to match a real Moodle course:
        // courseidnumber is now the canonical identity (works whether or not
        // a matching course exists), courseid becomes optional (null = no
        // matching course found), and coursename is an optional CSV-provided
        // display name used only when there's no real course to read a name
        // from. See chat notes: "コースが無くてもカリキュラムマップに落とし
        // 込めるように".
        $field = new xmldb_field('courseidnumber', XMLDB_TYPE_CHAR, '255', null, XMLDB_NOTNULL, null, '', 'axisid');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        $field = new xmldb_field('coursename', XMLDB_TYPE_CHAR, '255', null, null, null, null, 'courseidnumber');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // The existing 'courseid' index depends on this field, so Postgres
        // (and possibly others) refuses change_field_notnull() while it's
        // still there - drop it first, change the field, then rebuild it.
        $courseidindex = new xmldb_index('courseid', XMLDB_INDEX_NOTUNIQUE, ['courseid']);
        if ($dbman->index_exists($table, $courseidindex)) {
            $dbman->drop_index($table, $courseidindex);
        }

        $field = new xmldb_field('courseid', XMLDB_TYPE_INTEGER, '10', null, null, null, null, 'coursename');
        if ($dbman->field_exists($table, $field)) {
            $dbman->change_field_notnull($table, $field);
        }

        if (!$dbman->index_exists($table, $courseidindex)) {
            $dbman->add_index($table, $courseidindex);
        }

        $index = new xmldb_index('courseidnumber', XMLDB_INDEX_NOTUNIQUE, ['courseidnumber']);
        if (!$dbman->index_exists($table, $index)) {
            $dbman->add_index($table, $index);
        }

        upgrade_block_savepoint(true, 2026072608, 'curriculummap');
    }

    return true;
}
