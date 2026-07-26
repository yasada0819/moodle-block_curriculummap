<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

/**
 * Web service / AJAX function definitions for block_curriculummap.
 *
 * @package    block_curriculummap
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$functions = [
    'block_curriculummap_get_data' => [
        'classname'    => 'block_curriculummap\external\get_data',
        'methodname'   => 'execute',
        'description'  => 'Return the curriculum-map intermediate data model '
            . '(subjects[] + axis registry) built from Moodle competencies '
            . '(and, pending the DBM adapter, course category/milestone).',
        'type'         => 'read',
        'ajax'         => true,
        'capabilities' => 'block/curriculummap:view',
    ],
];
