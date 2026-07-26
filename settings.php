<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

/**
 * Global settings for block_curriculummap: mirrors manage.php's fields
 * (per-axis datasource + framework idnumber, category idnumber substring)
 * for actual site administrators. Managers reach the equivalent form via
 * manage.php instead, since this page is hardcoded by Moodle core to
 * require moodle/site:config - see classes/form/manage_form.php.
 *
 * @package    block_curriculummap
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

if ($ADMIN->fulltree) {

    foreach (['dp', 'core', 'milestone'] as $axisid) {
        $settings->add(new admin_setting_heading(
            "block_curriculummap/{$axisid}heading",
            get_string("axisheading_{$axisid}", 'block_curriculummap'),
            ''
        ));

        $settings->add(new admin_setting_configselect(
            "block_curriculummap/{$axisid}_datasource",
            get_string('datasource', 'block_curriculummap'),
            '',
            'competency',
            [
                'competency' => get_string('datasource_competency', 'block_curriculummap'),
                'csv'        => get_string('datasource_csv', 'block_curriculummap'),
            ]
        ));

        $settings->add(new admin_setting_configtext(
            "block_curriculummap/{$axisid}frameworkidnumber",
            get_string("settings_{$axisid}frameworkidnumber", 'block_curriculummap'),
            '',
            '',
            PARAM_RAW
        ));
    }

    $settings->add(new admin_setting_heading(
        'block_curriculummap/categoryheading',
        get_string('settings_categoryheading', 'block_curriculummap'),
        get_string('settings_categoryheading_desc', 'block_curriculummap')
    ));

    $settings->add(new admin_setting_configtext(
        'block_curriculummap/category_idnumber_offset',
        get_string('settings_category_idnumber_offset', 'block_curriculummap'),
        '',
        7,
        PARAM_INT
    ));

    $settings->add(new admin_setting_configtext(
        'block_curriculummap/category_idnumber_length',
        get_string('settings_category_idnumber_length', 'block_curriculummap'),
        '',
        2,
        PARAM_INT
    ));
}
