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
 * Upload form for a filled-in Excel template (spec §3.2 step 3).
 *
 * @package    tool_bulkcoursestudio
 * @copyright  2026 Bulk Course Studio
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class excel_upload_form extends \moodleform {
    /**
     * Build the form fields.
     */
    protected function definition() {
        $mform = $this->_form;

        $mform->addElement('filepicker', 'templatefile', get_string('uploadtemplate', 'tool_bulkcoursestudio'), null, [
            'maxbytes' => 10 * 1024 * 1024,
            'accepted_types' => ['.xlsx'],
        ]);
        $mform->addRule('templatefile', null, 'required', null, 'client');

        $this->add_action_buttons(true, get_string('uploadtemplate', 'tool_bulkcoursestudio'));
    }
}
