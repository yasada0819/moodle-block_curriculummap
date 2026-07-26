<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

/**
 * Curriculum map block.
 *
 * Where it's placed decides nothing on its own - the *displaymode* instance
 * setting (edit_form.php) decides whether get_content() renders:
 *   - 'link'  : compact text + a normal link to view.php (default)
 *   - 'modal' : compact text + a button that opens view.php's content in a
 *               popup (core/modal), no page navigation
 *   - 'full'  : the full cross-tab visualization inline, wherever the block sits
 *
 * @package    block_curriculummap
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

class block_curriculummap extends block_base {

    /**
     * @return void
     */
    public function init() {
        $this->title = get_string('pluginname', 'block_curriculummap');
    }

    /**
     * Global settings.php exists (for actual site admins). Managers reach
     * the equivalent framework-mapping form via manage.php instead - see
     * classes/form/manage_form.php for why.
     *
     * @return bool
     */
    public function has_config() {
        return true;
    }

    /**
     * Deliberately unrestricted: any location (dashboard, course, site
     * front page, etc). Behaviour differs via $config->displaymode, not
     * via where the block happens to be placed.
     *
     * @return array
     */
    public function applicable_formats() {
        return ['all' => true];
    }

    /**
     * @return bool
     */
    public function instance_allow_multiple() {
        return false;
    }

    /**
     * @return bool
     */
    public function instance_allow_config() {
        return true;
    }

    /**
     * @return stdClass|null
     */
    public function get_content() {
        if ($this->content !== null) {
            return $this->content;
        }

        $this->content = new stdClass();
        $this->content->text = '';
        $this->content->footer = '';

        if (!has_capability('block/curriculummap:view', $this->context)) {
            return $this->content;
        }

        $mode = $this->config->displaymode ?? 'link';
        $viewurl = new moodle_url('/blocks/curriculummap/view.php', ['instanceid' => $this->instance->id]);

        if ($mode === 'full') {
            $this->content->text = $this->page->get_renderer('block_curriculummap')->render_full($this->context);
        } else {
            $this->content->text = $this->page->get_renderer('block_curriculummap')->render_compact($viewurl, $mode);
        }

        if (has_capability('block/curriculummap:manage', context_system::instance())) {
            $manageurl = new moodle_url('/blocks/curriculummap/manage.php');
            $this->content->footer = \html_writer::link(
                $manageurl,
                get_string('settings_frameworksheading', 'block_curriculummap'),
                ['class' => 'small text-muted']
            );
        }

        return $this->content;
    }
}
