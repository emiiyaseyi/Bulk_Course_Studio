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
 * Step 2 of Flow B (spec §3.2): after an Excel template is parsed, any
 * resource/folder activity that named a "local_filename" in its row needs
 * the actual file. Rather than one upload field per activity (unwieldy for
 * a build with dozens of such rows), the admin drops every matching file
 * into a single filemanager here; step_matchfiles.php then matches each
 * uploaded file's own filename against the activities expecting it.
 *
 * @package    tool_bulkcoursestudio
 * @copyright  2026 Bulk Course Studio
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class matchfiles_form extends \moodleform {
    /**
     * Build the form fields.
     */
    protected function definition() {
        $mform = $this->_form;
        $buildid = $this->_customdata['buildid'];

        $mform->addElement('hidden', 'buildid', $buildid);
        $mform->setType('buildid', PARAM_INT);

        $mform->addElement(
            'filemanager',
            'batchfiles',
            get_string('matchfiles:batchfiles', 'tool_bulkcoursestudio'),
            null,
            [
                'subdirs' => 0,
                'maxfiles' => 50,
                'maxbytes' => 0,
                'accepted_types' => '*',
            ]
        );

        $this->add_action_buttons(true, get_string('matchfiles:matchandcontinue', 'tool_bulkcoursestudio'));
    }
}
