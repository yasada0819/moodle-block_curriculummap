<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

/**
 * English language strings for block_curriculummap.
 *
 * @package    block_curriculummap
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['pluginname']       = 'Curriculum map';
$string['curriculummap:addinstance']   = 'Add a new curriculum map block';
$string['curriculummap:myaddinstance'] = 'Add a new curriculum map block to the Dashboard';
$string['curriculummap:view']          = 'View the curriculum map';
$string['curriculummap:manage']        = 'Configure the curriculum map display mode and framework mapping';

$string['privacy:metadata'] = 'The curriculum map block only reads and aggregates structural course/competency data and stores no personal user data.';

// Instance configuration (edit_form.php).
$string['displaymode']       = 'Display mode';
$string['displaymode_link']  = 'Compact + link to a separate page (default)';
$string['displaymode_modal'] = 'Compact + open in a popup (modal)';
$string['displaymode_full']  = 'Full visualization inline';
$string['displaymode_managedonly'] = 'Only a Manager or Administrator can change this setting.';
$string['configtitle'] = 'Custom block title (optional)';

// Compact / link view.
$string['openfullview']  = 'Open curriculum map';
$string['viewpagetitle'] = 'Curriculum map';
$string['nodataconfigured'] = 'No competency framework is mapped yet. Ask a Manager to configure this in the block\'s global settings.';
$string['rendererpending'] = 'The cross-tab visualization engine has not been ported into this plugin yet (skeleton stage). Data fetching and permissions are wired up; rendering is the next increment.';

// Global settings.php (framework idnumber mapping).
$string['settings_frameworksheading'] = 'Framework mapping';
$string['settings_frameworksheading_desc'] = 'Map the Moodle competency frameworks used as the DP axis and the core-curriculum axis. Leave blank to hide that axis.';
$string['settings_dpframeworkidnumber'] = 'DP framework idnumber';
$string['settings_dpframeworkidnumber_desc'] = 'The idnumber of the competency framework representing the Diploma Policy (DP).';
$string['settings_coreframeworkidnumber'] = 'Core-curriculum framework idnumber';
$string['settings_coreframeworkidnumber_desc'] = 'The idnumber of the competency framework representing the national core curriculum.';
$string['settings_milestoneframeworkidnumber'] = 'Milestone framework idnumber';

// Per-instance override support (manage.php / edit_form.php-adjacent).
$string['manageinstancenote'] = 'These settings apply ONLY to this one block instance. Any axis left on "Use the site-wide default" keeps using the settings configured at the site level.';
$string['manageinstanceheading'] = 'Instance-specific settings for: {$a}';
$string['datasource_inherit'] = 'Use the site-wide default';
$string['category_inherit'] = 'Use the site-wide default for category extraction';
$string['csvimportintroinstance'] = 'Upload a CSV to overwrite the stored data for one axis of THIS block instance only (dp / core / milestone). This does not affect the site-wide shared data or other instances.';

// Per-axis datasource toggle (manage.php / settings.php).
$string['axisheading_dp'] = 'DP (Diploma Policy)';
$string['axisheading_core'] = 'Core curriculum';
$string['axisheading_milestone'] = 'Milestone';
$string['datasource'] = 'Data source';
$string['datasource_competency'] = 'Moodle Competency (live)';
$string['datasource_csv'] = 'CSV upload';
$string['managecsvnote'] = 'This axis reads from CSV data uploaded via "Import CSV data" below the framework-mapping form.';

// Category (course.idnumber substring) settings.
$string['settings_categoryheading'] = 'Subject category extraction';
$string['settings_categoryheading_desc'] = 'Category is derived from a substring of each course\'s idnumber (e.g. "2026_M_L3XXXX", offset 7, length 2 -> "L3"), not from a framework or CSV.';
$string['settings_category_idnumber_offset'] = 'Substring offset (0-indexed)';
$string['settings_category_idnumber_length'] = 'Substring length';

// manage.php: stored CSV data preview.
$string['csvdatapreview'] = 'Currently stored CSV data';
$string['gotocsvimport'] = 'Import CSV data';
$string['csvrowcount'] = '{$a} row(s) stored';
$string['csvnorowsyet'] = 'No CSV data stored for this axis yet.';
$string['csvcol_course'] = 'Course idnumber';
$string['csvcol_coursename'] = 'Course name';
$string['csvcol_itemidnumber'] = 'Item idnumber';
$string['csvcol_itemlabel'] = 'Item label';
$string['csvcol_parentlabel'] = 'Group / parent label';
$string['csvpreviewtruncated'] = 'Showing the first 50 of {$a} rows.';
$string['csvpreviewshowmore'] = 'Show {$a} more row(s)';

// csv_import.php.
$string['csvimporttitle'] = 'Import CSV data';
$string['csvimportintro'] = 'Upload a CSV to overwrite the stored data for one axis (dp / core / milestone). Existing rows for that axis are replaced entirely on confirm. A course_idnumber that doesn\'t match any existing Moodle course is fine - it becomes a "virtual" subject, using course_name (or the idnumber itself) as its display name.';
$string['csvaxisid'] = 'Which axis is this file for?';
$string['csvformatdesc'] = 'Columns: course_idnumber, item_idnumber, item_label, parent_label, course_name (parent_label and course_name are optional - leave parent_label blank for flat axes like milestone; course_name is only used when course_idnumber doesn\'t match a real course). One row per course-item pair; a course with multiple items just needs multiple rows.';
$string['csvfile'] = 'CSV file';
$string['csvuploadpreview'] = 'Preview';
$string['csvpreviewheading'] = 'Preview';
$string['csvpreviewsummary'] = '{$a->total} row(s): {$a->matched} matched an existing course, {$a->virtual} will import as virtual subjects, {$a->missing_fields} missing required fields (blocked).';
$string['csvcol_line'] = 'Line';
$string['csvcol_status'] = 'Status';
$string['csvstatus_matched'] = 'Matched existing course';
$string['csvstatus_virtual'] = 'No matching course (virtual)';
$string['csvstatus_missing_fields'] = 'Missing required fields';
$string['csvzeroimportablewarning'] = 'Every row is missing a required field, so nothing will be imported - but you can still confirm to clear out any existing data for this axis.';
$string['csvconfirmimport'] = 'Confirm import';
$string['csvimportdone'] = 'Import complete: {$a->inserted} row(s) imported, {$a->skipped} skipped.';

// Visualization (amd/src/curriculummap.js + templates/full.mustache).
$string['viz_rowaxis'] = 'Row axis';
$string['viz_colaxis'] = 'Column axis';
$string['viz_sortorder'] = 'Sort order';
$string['viz_asc'] = 'Ascending';
$string['viz_desc'] = 'Descending';
$string['viz_swapaxes'] = '⇄ Swap axes';
$string['viz_mode'] = 'Aggregation (totals)';
$string['viz_mode_total'] = 'Total';
$string['viz_mode_unique'] = 'Unique';
$string['viz_celldisplay'] = 'Cell display';
$string['viz_celldisplay_number'] = 'Number';
$string['viz_celldisplay_segments'] = 'Segments';
$string['viz_reset'] = 'Reset selection';
$string['viz_addfilteraxis'] = 'Add filter axis';
$string['viz_addfilterbtn'] = '+ Add';
$string['viz_note'] = 'Aggregation mode only affects row/column totals (whether a subject linked to multiple values is double-counted there), not the cells themselves. Filters can be added for multiple axes at once - AND across axes, OR within one axis\'s values.';
$string['viz_categoryaxis'] = 'Subject category';
$string['viz_gradeaxis'] = 'Grade';
$string['viz_majorsuffix'] = ' (major)';
$string['viz_total'] = 'Total';
$string['viz_selectall'] = 'Select all';
$string['viz_selectnone'] = 'Select none';
$string['viz_removefilter'] = '✕ Remove this filter';
$string['viz_clickforlist'] = 'Click a cell to see the matching subjects.';
$string['viz_backtolist'] = '← Back to list';
$string['viz_addtocompare'] = 'Add to comparison list';
$string['viz_comparelist'] = 'Comparison list ({$a})';
$string['viz_comparesearchplaceholder'] = 'Search by subject name to add...';
$string['viz_remove'] = 'Remove';
$string['viz_opencourse'] = 'Open course';
$string['viz_novaluelabel'] = 'Not set';
$string['viz_statline'] = 'Showing: {$a->shown} / {$a->total} subjects';
$string['viz_nomatch'] = 'No matching subjects.';
