<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

namespace block_curriculummap\form;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/formslib.php');

/**
 * Step 1 of the CSV import flow: pick which axis this file is for, and
 * upload the file. See csv_import.php for the full preview -> confirm flow,
 * mirroring the design memo's bulk-link tool pattern (CSV -> preview ->
 * confirm -> log).
 *
 * Expected columns: course_idnumber, item_idnumber, item_label, parent_label
 * (parent_label optional - used for major/minor grouping; leave blank for
 * flat axes like milestone). One row per course-item pair; a course with
 * multiple items just gets multiple rows.
 *
 * @package    block_curriculummap
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class csv_upload_form extends \moodleform {

    /**
     * @return void
     */
    protected function definition() {
        $mform = $this->_form;

        $mform->addElement('select', 'axisid', get_string('csvaxisid', 'block_curriculummap'), [
            'dp'        => get_string('axisheading_dp', 'block_curriculummap'),
            'core'      => get_string('axisheading_core', 'block_curriculummap'),
            'milestone' => get_string('axisheading_milestone', 'block_curriculummap'),
        ]);

        $mform->addElement('static', 'csvformatdesc', '', get_string('csvformatdesc', 'block_curriculummap'));

        $mform->addElement('filepicker', 'csvfile', get_string('csvfile', 'block_curriculummap'), null,
            ['accepted_types' => ['.csv', '.txt']]);
        $mform->addRule('csvfile', null, 'required');

        $this->add_action_buttons(false, get_string('csvuploadpreview', 'block_curriculummap'));
    }
}
