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
 * Step 2 of the CSV import flow: the parsed file is round-tripped through a
 * hidden field (base64) rather than re-uploaded, so the confirm step doesn't
 * need the user to select the file again. Small-scale data (design memo:
 * ~100-300 subjects), so this is fine without a session/draft-area approach.
 *
 * @package    block_curriculummap
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class csv_confirm_form extends \moodleform {

    /**
     * @return void
     */
    protected function definition() {
        $mform = $this->_form;

        $mform->addElement('hidden', 'axisid');
        $mform->setType('axisid', PARAM_ALPHA);

        $mform->addElement('hidden', 'csvcontentb64');
        $mform->setType('csvcontentb64', PARAM_RAW);

        $mform->addElement('hidden', 'step');
        $mform->setType('step', PARAM_ALPHA);
        $mform->setDefault('step', 'confirm');

        $this->add_action_buttons(true, get_string('csvconfirmimport', 'block_curriculummap'));
    }
}
