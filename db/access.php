<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

/**
 * Capability definitions for block_curriculummap.
 *
 * Design intent:
 * - view        : everyone, including guests, can see the visualization.
 * - manage      : only Manager (and Admin, who bypasses capability checks
 *                 entirely) can flip the block's display mode and edit the
 *                 DP/core-curriculum framework mapping in settings.php.
 * - addinstance / myaddinstance : left at Moodle's normal block defaults
 *                 (editingteacher can place the block like any other block).
 *                 See chat notes: placement is NOT restricted to admin/manager,
 *                 only the *behavioral* configuration (display mode) is.
 *
 * @package    block_curriculummap
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$capabilities = [

    'block/curriculummap:addinstance' => [
        'riskbitmask'  => RISK_SPAM | RISK_XSS,
        'captype'      => 'write',
        'contextlevel' => CONTEXT_BLOCK,
        'archetypes'   => [
            'editingteacher' => CAP_ALLOW,
            'manager'        => CAP_ALLOW,
        ],
        'clonepermissionsfrom' => 'moodle/site:manageblocks',
    ],

    'block/curriculummap:myaddinstance' => [
        'riskbitmask'  => RISK_SPAM | RISK_XSS,
        'captype'      => 'write',
        'contextlevel' => CONTEXT_SYSTEM,
        'archetypes'   => [
            'user' => CAP_ALLOW,
        ],
        'clonepermissionsfrom' => 'moodle/my:manageblocks',
    ],

    'block/curriculummap:view' => [
        'captype'      => 'read',
        'contextlevel' => CONTEXT_BLOCK,
        'archetypes'   => [
            'guest'          => CAP_ALLOW,
            'user'           => CAP_ALLOW,
            'student'        => CAP_ALLOW,
            'teacher'        => CAP_ALLOW,
            'editingteacher' => CAP_ALLOW,
            'manager'        => CAP_ALLOW,
        ],
    ],

    // Gates: (1) the "display mode" field in edit_form.php, and
    // (2) the global settings.php page (framework idnumber mapping).
    'block/curriculummap:manage' => [
        'captype'      => 'write',
        'contextlevel' => CONTEXT_SYSTEM,
        'archetypes'   => [
            'manager' => CAP_ALLOW,
        ],
    ],

];
