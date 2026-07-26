<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

/**
 * Full curriculum-map view. This is what 'link' mode navigates to, and
 * what 'modal' mode falls back to if JS is disabled. Deliberately a plain
 * page (not tied to any particular course), matching the design memo's
 * "generic, distributable visualization" framing.
 *
 * @package    block_curriculummap
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../config.php');

$instanceid = optional_param('instanceid', 0, PARAM_INT);

require_login();
$context = context_system::instance();
require_capability('block/curriculummap:view', $context);

$PAGE->set_url(new moodle_url('/blocks/curriculummap/view.php', ['instanceid' => $instanceid]));
$PAGE->set_context($context);
$PAGE->set_pagelayout('report');
$PAGE->set_title(get_string('viewpagetitle', 'block_curriculummap'));
$PAGE->set_heading(get_string('viewpagetitle', 'block_curriculummap'));

echo $OUTPUT->header();
echo $PAGE->get_renderer('block_curriculummap')->render_full($context);
echo $OUTPUT->footer();
