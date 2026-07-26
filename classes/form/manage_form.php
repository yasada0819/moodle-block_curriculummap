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
 * Used in TWO modes (see manage.php's $instanceid handling):
 * - 'site'     : editing the site-wide default (instanceid = 0). No
 *                "inherit" option makes sense here - there's nothing above
 *                the site default to inherit from.
 * - 'instance' : editing one block instance's overrides. Each axis defaults
 *                to 'inherit' (use the site-wide default untouched) unless a
 *                Manager explicitly picks 'competency' or 'csv' for that
 *                specific instance. This is what lets e.g. a Medicine
 *                faculty block and a Nursing faculty block point at
 *                different DP frameworks while both leave "milestone" on
 *                the shared site default.
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
        $mode = $this->_customdata['mode'] ?? 'site';
        $isinstance = ($mode === 'instance');

        if ($isinstance) {
            $mform->addElement('static', 'instancenote', '',
                get_string('manageinstancenote', 'block_curriculummap'));
        }

        foreach (self::AXES as $axisid) {
            $mform->addElement('header', "{$axisid}header",
                get_string("axisheading_{$axisid}", 'block_curriculummap'));
            $mform->setExpanded("{$axisid}header", true);

            $options = [];
            if ($isinstance) {
                $options['inherit'] = get_string('datasource_inherit', 'block_curriculummap');
            }
            $options['competency'] = get_string('datasource_competency', 'block_curriculummap');
            $options['csv'] = get_string('datasource_csv', 'block_curriculummap');

            $mform->addElement('select', "{$axisid}_datasource",
                get_string('datasource', 'block_curriculummap'), $options);
            $mform->setDefault("{$axisid}_datasource", $isinstance ? 'inherit' : 'competency');

            $mform->addElement('text', "{$axisid}frameworkidnumber",
                get_string("settings_{$axisid}frameworkidnumber", 'block_curriculummap'));
            $mform->setType("{$axisid}frameworkidnumber", PARAM_RAW);
            $mform->hideIf("{$axisid}frameworkidnumber", "{$axisid}_datasource", 'neq', 'competency');

            $mform->addElement('static', "{$axisid}csvnote", '',
                get_string('managecsvnote', 'block_curriculummap'));
            $mform->hideIf("{$axisid}csvnote", "{$axisid}_datasource", 'neq', 'csv');
        }

        $mform->addElement('header', 'categoryheader',
            get_string('settings_categoryheading', 'block_curriculummap'));
        $mform->setExpanded('categoryheader', true);

        $mform->addElement('static', 'categorydesc', '',
            get_string('settings_categoryheading_desc', 'block_curriculummap'));

        if ($isinstance) {
            $mform->addElement('advcheckbox', 'category_inherit',
                get_string('category_inherit', 'block_curriculummap'));
            $mform->setDefault('category_inherit', 1);
        }

        $mform->addElement('text', 'category_idnumber_offset',
            get_string('settings_category_idnumber_offset', 'block_curriculummap'), ['size' => 4]);
        $mform->setType('category_idnumber_offset', PARAM_INT);
        $mform->setDefault('category_idnumber_offset', 7);
        if ($isinstance) {
            $mform->hideIf('category_idnumber_offset', 'category_inherit', 'checked');
        }

        $mform->addElement('text', 'category_idnumber_length',
            get_string('settings_category_idnumber_length', 'block_curriculummap'), ['size' => 4]);
        $mform->setType('category_idnumber_length', PARAM_INT);
        $mform->setDefault('category_idnumber_length', 2);
        if ($isinstance) {
            $mform->hideIf('category_idnumber_length', 'category_inherit', 'checked');
        }

        $this->add_action_buttons();
    }
}
