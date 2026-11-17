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
 * Wizard step 1 — course details. Mirrors the fields of core's
 * course_edit_form closely enough for v1 (full name, short name, category,
 * summary, dates, visibility, completion tracking on/off) plus the course
 * image filemanager field, built on the same pattern
 * format_edwiservideoformat uses (file_prepare_standard_filemanager /
 * file_postupdate_standard_filemanager — called from the controller, not
 * this form, since they need the draft's file area itemid).
 *
 * Deliberately NOT a copy-paste of course_edit_form: that form is tightly
 * coupled to an existing $course object and course context, which a
 * not-yet-created draft doesn't have. This form captures the same data
 * into the draft JSON instead; build_manager::commit() is what finally
 * calls create_course()/update_course() with it.
 *
 * @package    tool_bulkcoursestudio
 * @copyright  2026 Bulk Course Studio
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class course_details_form extends \moodleform {
    /**
     * Build the form fields.
     */
    protected function definition() {
        $mform = $this->_form;

        $mform->addElement('text', 'fullname', get_string('fullnamecourse'));
        $mform->setType('fullname', PARAM_TEXT);
        $mform->addRule('fullname', null, 'required', null, 'client');

        $mform->addElement('text', 'shortname', get_string('shortnamecourse'));
        $mform->setType('shortname', PARAM_TEXT);
        $mform->addRule('shortname', null, 'required', null, 'client');

        $options = \core_course_category::make_categories_list();
        $mform->addElement('autocomplete', 'category', get_string('coursecategory'), $options);
        $mform->addRule('category', null, 'required', null, 'client');

        $mform->addElement('editor', 'summary_editor', get_string('coursesummary'));
        $mform->setType('summary_editor', PARAM_RAW);

        $mform->addElement('filemanager', 'courseimage', get_string('courseimage', 'tool_bulkcoursestudio'), null, [
            'subdirs' => 0,
            'maxfiles' => 1,
            'accepted_types' => ['web_image'],
        ]);

        $mform->addElement('date_time_selector', 'startdate', get_string('startdate'));
        $mform->addElement('date_time_selector', 'enddate', get_string('enddate'), ['optional' => true]);

        $mform->addElement('selectyesno', 'visible', get_string('coursevisibility'));
        $mform->setDefault('visible', 0); // Builds are always hidden until publish(); see build_manager.

        $mform->addElement('selectyesno', 'enablecompletion', get_string('enablecompletion'));
        $mform->setDefault('enablecompletion', 1);

        $this->add_action_buttons(true, get_string('next', 'tool_bulkcoursestudio'));
    }

    /**
     * Validate the submitted form data.
     *
     * @param array $data
     * @param array $files
     * @return array errors keyed by field name
     */
    public function validation($data, $files) {
        $errors = [];
        if (!empty($data['enddate']) && !empty($data['startdate']) && $data['enddate'] <= $data['startdate']) {
            $errors['enddate'] = get_string('preview:error:enddatebeforestart', 'tool_bulkcoursestudio');
        }
        return $errors;
    }
}
