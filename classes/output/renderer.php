<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

namespace block_curriculummap\output;

use moodle_url;
use context;

defined('MOODLE_INTERNAL') || die();

/**
 * Renderer for block_curriculummap. Keeps get_content() free of raw HTML,
 * per Moodle convention (moodle-plugin-development skill: "Never echo raw
 * HTML in business logic; always go through renderer or template").
 *
 * @package    block_curriculummap
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class renderer extends \plugin_renderer_base {

    /**
     * Compact block content: short description + either a plain link
     * (mode 'link') or a button the AMD module intercepts to open a modal
     * (mode 'modal'). The href is always a real URL either way, so 'modal'
     * still degrades gracefully with JS disabled.
     *
     * @param moodle_url $viewurl
     * @param string $mode 'link' | 'modal'
     * @return string
     */
    public function render_compact(moodle_url $viewurl, string $mode): string {
        $data = [
            'viewurl' => $viewurl->out(false),
            'ismodal' => ($mode === 'modal'),
            'label'   => get_string('openfullview', 'block_curriculummap'),
        ];
        $html = $this->render_from_template('block_curriculummap/compact', $data);

        if ($mode === 'modal') {
            $this->page->requires->js_call_amd('block_curriculummap/curriculummap', 'initModalTrigger');
        }

        return $html;
    }

    /**
     * Full inline visualization. Renders the container markup; the AMD
     * module fetches data via block_curriculummap_get_data and populates it.
     *
     * TODO: this currently renders the container + a "not yet implemented"
     * placeholder. The full cross-tab engine from the HTML prototype
     * (axis registry, matrix, sidebar) still needs porting into
     * amd/src/curriculummap.js - see chat notes, this is the next increment.
     *
     * @param context $context
     * @return string
     */
    public function render_full(context $context): string {
        $html = $this->render_from_template('block_curriculummap/full', [
            'region' => 'curriculummap-root-' . $context->id,
        ]);
        $this->page->requires->js_call_amd('block_curriculummap/curriculummap', 'init', [
            'curriculummap-root-' . $context->id,
        ]);
        return $html;
    }
}
