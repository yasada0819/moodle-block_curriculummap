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
$plugin->version   = 2026081903;          // YYYYMMDDXX - bumped: axis items (dp/core/milestone, CSV or competency sourced) now sorted by (group, idnumber) before use, instead of relying on raw CSV/competency-query order. Fixes DP asc/desc sort being wrong and the Ⅰ/Ⅱ/Ⅲ major band fragmenting when source rows weren't already grouped.
$plugin->requires  = 2024100700;          // Moodle 4.5 LTS baseline. Bump if targeting 5.x only.
$plugin->release   = '0.6.3';
$plugin->maturity  = MATURITY_ALPHA;      // Core visualization now works end-to-end; still alpha pending broader testing.
