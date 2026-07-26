<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

/**
 * Block instance configuration form.
 *
 * The 'displaymode' field only appears for users holding
 * block/curriculummap:manage in this block's context. A teacher who can
 * otherwise edit the block (move/resize/rename it, per the normal
 * moodle/block:edit capability) will simply not see this field at all -
 * the mode silently stays whatever a Manager/Admin last set it to
 * (defaulting to 'link').
 *
 * @package    block_curriculummap
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

class block_curriculummap_edit_form extends block_edit_form {

    /**
     * @param MoodleQuickForm $mform
     * @return void
     */
    protected function specific_definition($mform) {
        $mform->addElement('header', 'configheader', get_string('blocksettings', 'block'));

        if (has_capability('block/curriculummap:manage', $this->block->context)) {
            $mform->addElement('select', 'config_displaymode',
                get_string('displaymode', 'block_curriculummap'),
                [
                    'link'  => get_string('displaymode_link', 'block_curriculummap'),
                    'modal' => get_string('displaymode_modal', 'block_curriculummap'),
                    'full'  => get_string('displaymode_full', 'block_curriculummap'),
                ]
            );
            $mform->setDefault('config_displaymode', 'link');
            $mform->setType('config_displaymode', PARAM_ALPHA);
        }
        // Users without 'manage' simply see no display-mode control here;
        // the existing instance config value (or the 'link' default) is
        // preserved untouched on save.
    }
}
