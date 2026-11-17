<?php
// This file is part of Moodle - https://moodle.org/
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
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

namespace tool_bulkcoursestudio\form;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/formslib.php');

/**
 * Wizard step 2 — course format. Options are read from whatever formats are
 * actually installed on this site (spec §3.1 step 2), never a hardcoded
 * list, so a site with RemUI/Edwiser Video Format installed sees them here
 * automatically.
 *
 * Format-specific option fields (e.g. RemUI's course-card settings) are a
 * v2 item per the spec — this form only captures the format choice itself
 * for v1.
 *
 * @package    tool_bulkcoursestudio
 * @copyright  2026 Bulk Course Studio
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class format_form extends \moodleform {
    /**
     * Build the form fields.
     */
    protected function definition() {
        $mform = $this->_form;

        $formats = \core_component::get_plugin_list('format');
        $options = [];
        foreach (array_keys($formats) as $format) {
            $options[$format] = get_string('pluginname', 'format_' . $format);
        }
        asort($options);

        $mform->addElement('select', 'format', get_string('format'), $options);
        $mform->setDefault('format', 'topics');

        $this->add_action_buttons(true, get_string('next', 'tool_bulkcoursestudio'));
    }
}
