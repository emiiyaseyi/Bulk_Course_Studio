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
 * Add/edit one draft section (spec §3.1 step 3). The wizard calls this once
 * per "Add section" click rather than asking for a fixed section count
 * upfront — simpler to implement and lets the admin add more later without
 * a separate "change the count" step.
 *
 * @package    tool_bulkcoursestudio
 * @copyright  2026 Bulk Course Studio
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class section_form extends \moodleform {
    /**
     * Build the form fields.
     */
    protected function definition() {
        $mform = $this->_form;

        $mform->addElement('hidden', 'buildid');
        $mform->setType('buildid', PARAM_INT);

        $mform->addElement('hidden', 'sectionid');
        $mform->setType('sectionid', PARAM_INT);

        $mform->addElement('text', 'name', get_string('sectionname', 'tool_bulkcoursestudio'));
        $mform->setType('name', PARAM_TEXT);
        $mform->addRule('name', null, 'required', null, 'client');

        $mform->addElement('editor', 'summary_editor', get_string('sectionsummary', 'tool_bulkcoursestudio'));
        $mform->setType('summary_editor', PARAM_RAW);

        $this->add_action_buttons(true, get_string('savesection', 'tool_bulkcoursestudio'));
    }
}
