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
 * Two modes, both reachable without moodle/site:config (see
 * classes/form/manage_form.php for why this exists as a standalone page):
 * - No instanceid (or instanceid=0): edits the SITE-WIDE DEFAULT, stored as
 *   plugin config (get_config/set_config('block_curriculummap', ...)).
 *   Capability checked at the system context.
 * - instanceid=<block instance id>: edits that ONE block instance's
 *   overrides, stored in the block's own instance config (via Moodle's
 *   block_instance_config_save() API, same mechanism edit_form.php would
 *   use). Capability checked at that instance's own block context, so a
 *   Manager can be scoped to just their faculty's block if desired.
 *
 * Also shows a read-only preview of whatever is currently stored in
 * block_curriculummap_link for the relevant scope, so a Manager can
 * sanity-check an upload without needing DB access.
 *
 * @package    block_curriculummap
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../config.php');

use block_curriculummap\form\manage_form;

global $DB, $OUTPUT, $PAGE;

$instanceid = optional_param('instanceid', 0, PARAM_INT);

require_login();

$blockinstance = null;
if ($instanceid) {
    $instancerecord = $DB->get_record('block_instances', ['id' => $instanceid, 'blockname' => 'curriculummap'],
        '*', MUST_EXIST);
    $blockinstance = block_instance('curriculummap', $instancerecord);
    $context = $blockinstance->context;
} else {
    $context = context_system::instance();
}
require_capability('block/curriculummap:manage', $context);

$PAGE->set_url(new moodle_url('/blocks/curriculummap/manage.php', ['instanceid' => $instanceid]));
$PAGE->set_context($context);
$PAGE->set_pagelayout('admin');
$title = get_string('curriculummap:manage', 'block_curriculummap');
$PAGE->set_title($title);
$PAGE->set_heading($title);

$mode = $instanceid ? 'instance' : 'site';
$returnurl = new moodle_url('/blocks/curriculummap/manage.php', ['instanceid' => $instanceid]);

$mform = new manage_form($PAGE->url, ['mode' => $mode]);

// ---- load current values ----
if ($mode === 'site') {
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
} else {
    $iconfig = $blockinstance->config ?: new stdClass();
    $currentdata = ['category_idnumber_offset' => 7, 'category_idnumber_length' => 2, 'category_inherit' => 1];
    foreach (manage_form::AXES as $axisid) {
        $currentdata["{$axisid}_datasource"] = $iconfig->{"{$axisid}_datasource"} ?? 'inherit';
        $currentdata["{$axisid}frameworkidnumber"] = $iconfig->{"{$axisid}frameworkidnumber"} ?? '';
    }
    if (isset($iconfig->category_idnumber_offset)) {
        $currentdata['category_idnumber_offset'] = $iconfig->category_idnumber_offset;
        $currentdata['category_inherit'] = 0;
    }
    if (isset($iconfig->category_idnumber_length)) {
        $currentdata['category_idnumber_length'] = $iconfig->category_idnumber_length;
    }
}
$mform->set_data($currentdata);

// ---- handle submit ----
if ($mform->is_cancelled()) {
    redirect(new moodle_url('/my/'));
} else if ($data = $mform->get_data()) {
    if ($mode === 'site') {
        foreach (manage_form::AXES as $axisid) {
            set_config("{$axisid}_datasource", $data->{"{$axisid}_datasource"}, 'block_curriculummap');
            set_config("{$axisid}frameworkidnumber", trim($data->{"{$axisid}frameworkidnumber"}), 'block_curriculummap');
        }
        set_config('category_idnumber_offset', (int)$data->category_idnumber_offset, 'block_curriculummap');
        set_config('category_idnumber_length', (int)$data->category_idnumber_length, 'block_curriculummap');
    } else {
        $newconfig = $blockinstance->config ? clone $blockinstance->config : new stdClass();
        foreach (manage_form::AXES as $axisid) {
            $newconfig->{"{$axisid}_datasource"} = $data->{"{$axisid}_datasource"};
            $newconfig->{"{$axisid}frameworkidnumber"} = trim($data->{"{$axisid}frameworkidnumber"});
        }
        if (!empty($data->category_inherit)) {
            unset($newconfig->category_idnumber_offset);
            unset($newconfig->category_idnumber_length);
        } else {
            $newconfig->category_idnumber_offset = (int)$data->category_idnumber_offset;
            $newconfig->category_idnumber_length = (int)$data->category_idnumber_length;
        }
        $blockinstance->instance_config_save($newconfig);
    }
    redirect($returnurl, get_string('changessaved'), null, \core\output\notification::NOTIFY_SUCCESS);
}

echo $OUTPUT->header();
echo $OUTPUT->heading($title);
if ($mode === 'instance') {
    echo html_writer::tag('p', get_string('manageinstanceheading', 'block_curriculummap', format_string($blockinstance->title ?: $title)),
        ['class' => 'text-muted']);
}
$mform->display();

// ---- read-only preview of CSV-sourced data currently stored, for this scope ----
echo $OUTPUT->heading(get_string('csvdatapreview', 'block_curriculummap'), 3);
$csvimporturl = new moodle_url('/blocks/curriculummap/csv_import.php', ['instanceid' => $instanceid]);
echo html_writer::div(
    html_writer::link($csvimporturl, get_string('gotocsvimport', 'block_curriculummap'), ['class' => 'btn btn-secondary']),
    'mb-3'
);

$scopeparams = $instanceid ? ['instanceid' => $instanceid] : ['instanceid' => null];

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
    $conditions = array_merge(['axisid' => $axisid], $scopeparams);
    $count = $DB->count_records('block_curriculummap_link', $conditions);
    echo $OUTPUT->heading(get_string("axisheading_{$axisid}", 'block_curriculummap')
        . ' - ' . get_string('csvrowcount', 'block_curriculummap', $count), 4);

    if ($count === 0) {
        echo html_writer::tag('p', get_string('csvnorowsyet', 'block_curriculummap'), ['class' => 'text-muted small']);
        continue;
    }

    $hardcap = 500;
    $rows = array_values($DB->get_records('block_curriculummap_link', $conditions, 'courseidnumber ASC',
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
