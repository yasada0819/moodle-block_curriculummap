<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Version details for block_curriculummap.
 *
 * @package    block_curriculummap
 * @copyright  2026 (design collaboration)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$plugin->component = 'block_curriculummap';
$plugin->version   = 2026081902;          // YYYYMMDDXX - bumped: added "grade" (school year) as a new single-valued axis, extracted from course.idnumber's underscore-delimited "M<digits>" token (robust to the varying digit-count of the preceding "L" segment). Same subject.grade shape/pattern as category; a subject spanning two grades intentionally stays two separate Moodle courses/rows, not a multi-valued axis.
$plugin->requires  = 2024100700;          // Moodle 4.5 LTS baseline. Bump if targeting 5.x only.
$plugin->release   = '0.6.2';
$plugin->maturity  = MATURITY_ALPHA;      // Core visualization now works end-to-end; still alpha pending broader testing.
