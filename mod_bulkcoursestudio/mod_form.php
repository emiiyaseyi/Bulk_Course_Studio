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

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/course/moodleform_mod.php');

/**
 * Settings form for a single mod_bulkcoursestudio instance. This is the
 * exact native mod_form.php that tool_bulkcoursestudio's wizard embeds
 * for this module type (spec §3.1 step 4) — no bespoke form is kept in
 * the tool_ component for this activity, by design.
 *
 * @package    mod_bulkcoursestudio
 * @copyright  2026 Bulk Course Studio
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class mod_bulkcoursestudio_mod_form extends moodleform_mod {
    /**
     * Build the form fields.
     */
    public function definition() {
        $mform = $this->_form;

        $mform->addElement('text', 'name', get_string('activityname', 'mod_bulkcoursestudio'));
        $mform->setType('name', PARAM_TEXT);
        $mform->addRule('name', null, 'required', null, 'client');

        $this->standard_intro_elements();

        $mform->addElement('header', 'embedsection', get_string('embedsection', 'mod_bulkcoursestudio'));

        $mform->addElement('text', 'embedurl', get_string('embedurl', 'mod_bulkcoursestudio'), ['size' => '64']);
        $mform->setType('embedurl', PARAM_URL);
        $mform->addRule('embedurl', null, 'required', null, 'client');
        $mform->addHelpButton('embedurl', 'embedurl', 'mod_bulkcoursestudio');

        // TODO MDL-0: AJAX-driven live preview and sharing notice
        // (embed_resolver::get_sharing_notice()) rendered below this field
        // via an AMD module, both in this native settings form and inside
        // the tool_ wizard's embedded copy of it.
        // wizard's embedded copy of it.

        $this->standard_coursemodule_elements();
        $this->add_action_buttons();
    }

    /**
     * Custom completion rule: require a minimum number of seconds watched,
     * in addition to (or instead of) core's view-based completion.
     */
    public function add_completion_rules() {
        $mform = $this->_form;

        $mform->addElement(
            'checkbox',
            'completionminsecondsenabled',
            get_string('completionminseconds', 'mod_bulkcoursestudio'),
            ''
        );
        $mform->addElement(
            'duration',
            'completionminseconds',
            '',
            ['optional' => false],
            ['defaultunit' => 1]
        );
        $mform->disabledIf('completionminseconds', 'completionminsecondsenabled', 'notchecked');

        return ['completionminsecondsenabled'];
    }

    /**
     * Whether the "minimum seconds watched" completion rule is enabled for
     * a given set of submitted form data.
     *
     * @param array $data
     * @return bool
     */
    public function completion_rule_enabled($data) {
        return !empty($data['completionminsecondsenabled']) && $data['completionminseconds'] > 0;
    }
}
