<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

/**
 * CSV import for CSV-sourced axis data (dp / core / milestone), following
 * the design memo's bulk-link tool pattern: upload -> preview (with
 * per-row status) -> confirm -> overwrite that axis's rows -> log summary.
 *
 * A course_idnumber that doesn't match any real Moodle course is NOT an
 * error here (chat notes: CSV-sourced axes can describe subjects that don't
 * exist in Moodle yet) - those rows still import as "virtual" subjects,
 * using the optional course_name column (or the idnumber itself) as their
 * display name. Only a genuinely missing required field blocks a row.
 *
 * @package    block_curriculummap
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../config.php');

use block_curriculummap\form\csv_upload_form;
use block_curriculummap\form\csv_confirm_form;
use block_curriculummap\local\csv_link_parser;

require_login();
$context = context_system::instance();
require_capability('block/curriculummap:manage', $context);

$PAGE->set_url(new moodle_url('/blocks/curriculummap/csv_import.php'));
$PAGE->set_context($context);
$PAGE->set_pagelayout('admin');
$PAGE->set_title(get_string('csvimporttitle', 'block_curriculummap'));
$PAGE->set_heading(get_string('csvimporttitle', 'block_curriculummap'));

$manageurl = new moodle_url('/blocks/curriculummap/manage.php');
$step = optional_param('step', '', PARAM_ALPHA);

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('csvimporttitle', 'block_curriculummap'));

if ($step === 'confirm') {
    // ---- Step 2: confirm and import ----
    $confirmform = new csv_confirm_form();

    if ($confirmform->is_cancelled()) {
        redirect($manageurl);
    } else if ($data = $confirmform->get_data()) {
        global $DB;
        $axisid = $data->axisid;
        $content = base64_decode($data->csvcontentb64);
        $rows = csv_link_parser::parse($content);

        $DB->delete_records('block_curriculummap_link', ['axisid' => $axisid]);

        $now = time();
        $inserted = 0;
        foreach ($rows as $row) {
            if (!in_array($row->status, csv_link_parser::IMPORTABLE_STATUSES, true)) {
                continue;
            }
            $DB->insert_record('block_curriculummap_link', (object)[
                'axisid'         => $axisid,
                'courseidnumber' => $row->course_idnumber,
                'coursename'     => $row->course_name,
                'courseid'       => $row->courseid, // null for virtual subjects.
                'itemidnumber'   => $row->item_idnumber,
                'itemlabel'      => $row->item_label,
                'parentlabel'    => $row->parent_label,
                'timecreated'    => $now,
            ]);
            $inserted++;
        }

        $summary = csv_link_parser::summarize($rows);
        redirect($manageurl, get_string('csvimportdone', 'block_curriculummap', (object)[
            'inserted' => $inserted,
            'skipped'  => $summary['total'] - $inserted,
        ]), null, \core\output\notification::NOTIFY_SUCCESS);
    }

    // Re-show preview if confirm form was displayed via GET/back-navigation without data.
    redirect(new moodle_url('/blocks/curriculummap/csv_import.php'));

} else {
    // ---- Step 1: upload, or show preview after upload ----
    $uploadform = new csv_upload_form();

    if ($data = $uploadform->get_data()) {
        $content = $uploadform->get_file_content('csvfile');
        $rows = csv_link_parser::parse($content);
        $summary = csv_link_parser::summarize($rows);

        echo $OUTPUT->heading(get_string('csvpreviewheading', 'block_curriculummap'), 3);
        echo html_writer::tag('p', get_string('csvpreviewsummary', 'block_curriculummap', (object)$summary));

        $table = new html_table();
        $table->head = [
            get_string('csvcol_line', 'block_curriculummap'),
            get_string('csvcol_course', 'block_curriculummap'),
            get_string('csvcol_coursename', 'block_curriculummap'),
            get_string('csvcol_itemidnumber', 'block_curriculummap'),
            get_string('csvcol_itemlabel', 'block_curriculummap'),
            get_string('csvcol_parentlabel', 'block_curriculummap'),
            get_string('csvcol_status', 'block_curriculummap'),
        ];
        foreach ($rows as $row) {
            $statuslabel = get_string('csvstatus_' . $row->status, 'block_curriculummap');
            $rowclass = $row->status === csv_link_parser::STATUS_MISSING_FIELDS ? 'table-danger'
                : ($row->status === csv_link_parser::STATUS_VIRTUAL ? 'table-info' : '');
            $table->rowclasses[] = $rowclass;
            $table->data[] = [
                $row->line, $row->course_idnumber, $row->course_name,
                $row->item_idnumber, $row->item_label, $row->parent_label, $statuslabel,
            ];
        }
        echo html_writer::table($table);

        $importablecount = $summary['matched'] + $summary['virtual'];
        if ($importablecount === 0) {
            echo html_writer::tag('p', get_string('csvzeroimportablewarning', 'block_curriculummap'),
                ['class' => 'text-warning']);
        }

        $confirmform = new csv_confirm_form();
        $confirmform->set_data([
            'axisid'        => $data->axisid,
            'csvcontentb64' => base64_encode($content),
            'step'          => 'confirm',
        ]);
        $confirmform->display();
    } else {
        echo html_writer::tag('p', get_string('csvimportintro', 'block_curriculummap'));
        $uploadform->display();
    }
}

echo $OUTPUT->footer();
