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
 * Manager-facing form for:
 * - per-axis (dp / core / milestone) datasource choice (competency vs csv)
 *   and the framework idnumber used when datasource is 'competency'
 * - the course-idnumber substring position used to derive "category"
 *   (see chat notes: e.g. "2026_M_L3XXXX" -> characters 8-9 -> "L3")
 *
 * Exists as a standalone form (see manage.php) specifically because
 * Moodle's standard block settings.php page is hardcoded to require
 * moodle/site:config, which a Manager archetype does not hold by default -
 * this form is instead gated by our own 'block/curriculummap:manage'.
 *
 * CSV data itself is uploaded separately via csv_import.php - this form
 * only controls which source get_data.php reads from for each axis.
 *
 * @package    block_curriculummap
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class manage_form extends \moodleform {

    /** @var array Axis ids that have a competency/csv datasource toggle. */
    const AXES = ['dp', 'core', 'milestone'];

    /**
     * @return void
     */
    protected function definition() {
        $mform = $this->_form;

        foreach (self::AXES as $axisid) {
            $mform->addElement('header', "{$axisid}header",
                get_string("axisheading_{$axisid}", 'block_curriculummap'));
            $mform->setExpanded("{$axisid}header", true);

            $mform->addElement('select', "{$axisid}_datasource",
                get_string('datasource', 'block_curriculummap'),
                [
                    'competency' => get_string('datasource_competency', 'block_curriculummap'),
                    'csv'        => get_string('datasource_csv', 'block_curriculummap'),
                ]
            );
            $mform->setDefault("{$axisid}_datasource", 'competency');

            $mform->addElement('text', "{$axisid}frameworkidnumber",
                get_string("settings_{$axisid}frameworkidnumber", 'block_curriculummap'));
            $mform->setType("{$axisid}frameworkidnumber", PARAM_RAW);
            $mform->hideIf("{$axisid}frameworkidnumber", "{$axisid}_datasource", 'eq', 'csv');

            $mform->addElement('static', "{$axisid}csvnote", '',
                get_string('managecsvnote', 'block_curriculummap'));
            $mform->hideIf("{$axisid}csvnote", "{$axisid}_datasource", 'eq', 'competency');
        }

        $mform->addElement('header', 'categoryheader',
            get_string('settings_categoryheading', 'block_curriculummap'));
        $mform->setExpanded('categoryheader', true);

        $mform->addElement('static', 'categorydesc', '',
            get_string('settings_categoryheading_desc', 'block_curriculummap'));

        $mform->addElement('text', 'category_idnumber_offset',
            get_string('settings_category_idnumber_offset', 'block_curriculummap'), ['size' => 4]);
        $mform->setType('category_idnumber_offset', PARAM_INT);
        $mform->setDefault('category_idnumber_offset', 7);

        $mform->addElement('text', 'category_idnumber_length',
            get_string('settings_category_idnumber_length', 'block_curriculummap'), ['size' => 4]);
        $mform->setType('category_idnumber_length', PARAM_INT);
        $mform->setDefault('category_idnumber_length', 2);

        $this->add_action_buttons();
    }
}
