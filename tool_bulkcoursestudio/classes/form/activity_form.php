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
 * Add/edit one draft activity (spec §3.1 step 4).
 *
 * HONEST LIMITATION, flagged rather than hidden: the spec's intent was to
 * embed each activity type's own native mod_form.php so every setting that
 * type's "Add an activity" screen offers is available here too. True 1:1
 * embedding of an arbitrary module's mod_form outside the real
 * course/modedit.php flow (which supplies a live $course, $cm, section
 * context, and draft file-area handling that many mod_forms assume) is a
 * substantial per-module integration effort, and not something safe to
 * fake as "done" without testing against a real Moodle instance for each
 * module type.
 *
 * v1 approach instead: the fields every module shares (name, description,
 * completion) are captured directly here, plus an "Advanced settings
 * (JSON)" field for anything a specific module's own form would otherwise
 * ask for (e.g. a quiz's grading method, an assignment's due date). Those
 * JSON keys are merged directly into the draft's moduledata and passed
 * straight through to add_moduleinfo()/update_moduleinfo() by
 * build_manager::commit_activity() — so the commit path already supports
 * full per-type settings, it's only this form's UI for them that's
 * simplified for v1. Promoting specific modules (starting with the v1
 * scope list: assign, quiz, forum, page, url, resource, folder) to a
 * proper dedicated sub-form each is the natural v1.1 follow-up, now that
 * the data path for it already exists.
 *
 * mod_bulkcoursestudio itself gets a first-class field here (embedurl)
 * rather than going through the JSON fallback, since it's this plugin's
 * own companion module and its one meaningful setting is simple enough to
 * surface directly.
 *
 * @package    tool_bulkcoursestudio
 * @copyright  2026 Bulk Course Studio
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class activity_form extends \moodleform {
    /**
     * Build the form fields.
     */
    protected function definition() {
        $mform = $this->_form;

        $mform->addElement('hidden', 'sectionid');
        $mform->setType('sectionid', PARAM_INT);

        $mform->addElement('hidden', 'activityid');
        $mform->setType('activityid', PARAM_INT);

        $modoptions = $this->get_activity_type_options();
        $mform->addElement('select', 'modname', get_string('activitytype', 'tool_bulkcoursestudio'), $modoptions);
        $mform->addRule('modname', null, 'required', null, 'client');

        $mform->addElement('text', 'name', get_string('activityname', 'tool_bulkcoursestudio'));
        $mform->setType('name', PARAM_TEXT);
        $mform->addRule('name', null, 'required', null, 'client');

        $mform->addElement('editor', 'intro_editor', get_string('activitydescription', 'tool_bulkcoursestudio'));
        $mform->setType('intro_editor', PARAM_RAW);

        // Only meaningful when modname = bulkcoursestudio; shown/hidden
        // client-side via hideIf so the field still posts a value either
        // way (kept simple for v1 rather than per-type dynamic forms).
        $mform->addElement('text', 'embedurl', get_string('embedurl', 'mod_bulkcoursestudio'), ['size' => '64']);
        $mform->setType('embedurl', PARAM_URL);
        $mform->hideIf('embedurl', 'modname', 'neq', 'bulkcoursestudio');

        // File-upload activities (resource/folder) — same filemanager
        // pattern as the course image field, per spec §3.1 step 1a.
        $mform->addElement('filemanager', 'activityfiles', get_string('activityfiles', 'tool_bulkcoursestudio'), null, [
            'subdirs' => 1,
            'maxfiles' => -1,
        ]);
        $mform->hideIf('activityfiles', 'modname', 'neq', 'resource');
        $mform->hideIf('activityfiles', 'modname', 'neq', 'folder');

        $mform->addElement(
            'textarea',
            'advancedsettingsjson',
            get_string('advancedsettingsjson', 'tool_bulkcoursestudio'),
            ['rows' => 4, 'cols' => 60]
        );
        $mform->addHelpButton('advancedsettingsjson', 'advancedsettingsjson', 'tool_bulkcoursestudio');

        $mform->addElement('header', 'completionheader', get_string('completion', 'completion'));
        $mform->addElement('selectyesno', 'completionview', get_string('completionview', 'tool_bulkcoursestudio'));
        $mform->addElement(
            'duration',
            'completionminseconds',
            get_string('completionminseconds', 'mod_bulkcoursestudio'),
            ['optional' => true]
        );
        $mform->hideIf('completionminseconds', 'modname', 'neq', 'bulkcoursestudio');

        $this->add_action_buttons(true, get_string('saveactivity', 'tool_bulkcoursestudio'));
    }

    /**
     * modname => display name, restricted to installed + visible mod_*
     * plugins — exactly what spec §3.1 step 4 calls for ("any installed
     * mod_*"), with the v1-hardened list (spec §8) given no special
     * treatment here; they all come from the same loop.
     */
    private function get_activity_type_options(): array {
        $options = [];
        $plugins = \core_plugin_manager::instance()->get_plugins_of_type('mod');
        foreach ($plugins as $plugin) {
            if ($plugin->is_enabled()) {
                $options[$plugin->name] = $plugin->displayname;
            }
        }
        asort($options);
        return $options;
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

        if (($data['modname'] ?? '') === 'bulkcoursestudio' && empty($data['embedurl'])) {
            $errors['embedurl'] = get_string('required');
        }

        if (!empty($data['advancedsettingsjson'])) {
            json_decode($data['advancedsettingsjson'], true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                $errors['advancedsettingsjson'] = get_string('invalidjson', 'tool_bulkcoursestudio');
            }
        }

        return $errors;
    }
}
