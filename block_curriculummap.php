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
     * Applies the per-instance custom title (edit_form.php's config_title),
     * if the person editing this instance set one - otherwise falls back to
     * the plugin name. Matters most once instance_allow_multiple() lets
     * several of these sit on one page (e.g. a Medicine map and a Nursing
     * map side by side) and need to be told apart at a glance.
     *
     * @return void
     */
    public function specialization() {
        if (!empty($this->config->title)) {
            $this->title = format_string($this->config->title);
        } else {
            $this->title = get_string('pluginname', 'block_curriculummap');
        }
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
     * Multiple instances on one page are supported: each gets its own
     * context (and therefore its own DOM container id and its own
     * instanceid-scoped settings/CSV data - see get_data.php), so they
     * don't collide. Give each a distinct config_title (edit_form.php) to
     * tell them apart.
     *
     * @return bool
     */
    public function instance_allow_multiple() {
        return true;
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
            $this->content->text = $this->page->get_renderer('block_curriculummap')
                ->render_full($this->context, $this->instance->id);
        } else {
            $this->content->text = $this->page->get_renderer('block_curriculummap')
                ->render_compact($viewurl, $mode, $this->instance->id);
        }

        // Checked at this block's own context (not system context) so a
        // Manager scoped to just this one instance (see manage.php) also
        // sees the link, not only a site-wide Manager.
        if (has_capability('block/curriculummap:manage', $this->context)) {
            $manageurl = new moodle_url('/blocks/curriculummap/manage.php', ['instanceid' => $this->instance->id]);
            $this->content->footer = \html_writer::link(
                $manageurl,
                get_string('settings_frameworksheading', 'block_curriculummap'),
                ['class' => 'small text-muted']
            );
        }

        return $this->content;
    }
}
