<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

/**
 * Manager-facing configuration page: per-axis datasource (competency/csv) +
 * framework idnumber mapping + category idnumber substring settings.
 *
 * Also shows a read-only preview of whatever is currently stored in
 * block_curriculummap_link (CSV-sourced data), per axis, so a Manager can
 * sanity-check an upload without needing DB access. See classes/form/manage_form.php
 * for why this exists as a standalone page rather than the standard block
 * settings.php.
 *
 * @package    block_curriculummap
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../config.php');

use block_curriculummap\form\manage_form;

require_login();
$context = context_system::instance();
require_capability('block/curriculummap:manage', $context);

$PAGE->set_url(new moodle_url('/blocks/curriculummap/manage.php'));
$PAGE->set_context($context);
$PAGE->set_pagelayout('admin');
$PAGE->set_title(get_string('curriculummap:manage', 'block_curriculummap'));
$PAGE->set_heading(get_string('curriculummap:manage', 'block_curriculummap'));

$returnurl = new moodle_url('/blocks/curriculummap/manage.php');

$mform = new manage_form();

$currentdata = ['category_idnumber_offset' => 7, 'category_idnumber_length' => 2];
foreach (manage_form::AXES as $axisid) {
    $currentdata["{$axisid}_datasource"] = get_config('block_curriculummap', "{$axisid}_datasource") ?: 'competency';
    $currentdata["{$axisid}frameworkidnumber"] = get_config('block_curriculummap', "{$axisid}frameworkidnumber");
}
$offset = get_config('block_curriculummap', 'category_idnumber_offset');
$length = get_config('block_curriculummap', 'category_idnumber_length');
if ($offset !== false && $offset !== '') {
    $currentdata['category_idnumber_offset'] = $offset;
}
if ($length !== false && $length !== '') {
    $currentdata['category_idnumber_length'] = $length;
}
$mform->set_data($currentdata);

if ($mform->is_cancelled()) {
    redirect(new moodle_url('/my/'));
} else if ($data = $mform->get_data()) {
    foreach (manage_form::AXES as $axisid) {
        set_config("{$axisid}_datasource", $data->{"{$axisid}_datasource"}, 'block_curriculummap');
        set_config("{$axisid}frameworkidnumber", trim($data->{"{$axisid}frameworkidnumber"}), 'block_curriculummap');
    }
    set_config('category_idnumber_offset', (int)$data->category_idnumber_offset, 'block_curriculummap');
    set_config('category_idnumber_length', (int)$data->category_idnumber_length, 'block_curriculummap');
    redirect($returnurl, get_string('changessaved'), null, \core\output\notification::NOTIFY_SUCCESS);
}

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('curriculummap:manage', 'block_curriculummap'));
$mform->display();

// Read-only preview of CSV-sourced data currently stored, per axis.
global $DB;
echo $OUTPUT->heading(get_string('csvdatapreview', 'block_curriculummap'), 3);
$csvimporturl = new moodle_url('/blocks/curriculummap/csv_import.php');
echo html_writer::div(
    html_writer::link($csvimporturl, get_string('gotocsvimport', 'block_curriculummap'), ['class' => 'btn btn-secondary']),
    'mb-3'
);

/**
 * Builds the course/item preview table shared by the always-visible and
 * collapsed <details> sections.
 *
 * @param array $rows Rows from block_curriculummap_link.
 * @return html_table
 */
$buildpreviewtable = function(array $rows): html_table {
    $table = new html_table();
    $table->head = [
        get_string('csvcol_course', 'block_curriculummap'),
        get_string('csvcol_coursename', 'block_curriculummap'),
        get_string('csvcol_itemidnumber', 'block_curriculummap'),
        get_string('csvcol_itemlabel', 'block_curriculummap'),
        get_string('csvcol_parentlabel', 'block_curriculummap'),
    ];
    foreach ($rows as $row) {
        $namedisplay = $row->coursename;
        if ($row->courseid) {
            try {
                $namedisplay = format_string(get_course($row->courseid)->fullname) . ' ('
                    . get_string('csvstatus_matched', 'block_curriculummap') . ')';
            } catch (\Exception $e) {
                unset($e);
            }
        } else {
            $namedisplay = ($row->coursename !== '' ? $row->coursename : $row->courseidnumber)
                . ' (' . get_string('csvstatus_virtual', 'block_curriculummap') . ')';
        }
        $table->data[] = [$row->courseidnumber, $namedisplay, $row->itemidnumber, $row->itemlabel, $row->parentlabel];
    }
    return $table;
};

foreach (manage_form::AXES as $axisid) {
    $count = $DB->count_records('block_curriculummap_link', ['axisid' => $axisid]);
    echo $OUTPUT->heading(get_string("axisheading_{$axisid}", 'block_curriculummap')
        . ' - ' . get_string('csvrowcount', 'block_curriculummap', $count), 4);

    if ($count === 0) {
        echo html_writer::tag('p', get_string('csvnorowsyet', 'block_curriculummap'), ['class' => 'text-muted small']);
        continue;
    }

    // Sanity cap - collapsing handles the "too much on screen" concern, this
    // just guards against an unreasonably large table if something odd got
    // imported.
    $hardcap = 500;
    $rows = array_values($DB->get_records('block_curriculummap_link', ['axisid' => $axisid], 'courseidnumber ASC',
        '*', 0, $hardcap));

    $visiblerows = array_slice($rows, 0, 10);
    $hiddenrows = array_slice($rows, 10);

    echo html_writer::table($buildpreviewtable($visiblerows));

    if (!empty($hiddenrows)) {
        $summary = html_writer::tag('summary',
            get_string('csvpreviewshowmore', 'block_curriculummap', count($hiddenrows)));
        $hiddentable = html_writer::table($buildpreviewtable($hiddenrows));
        echo html_writer::tag('details', $summary . $hiddentable, ['class' => 'mb-3']);
    }

    if ($count > $hardcap) {
        echo html_writer::tag('p', get_string('csvpreviewtruncated', 'block_curriculummap', $count),
            ['class' => 'text-muted small']);
    }
}

echo $OUTPUT->footer();
