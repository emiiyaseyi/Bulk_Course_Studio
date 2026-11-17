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

/**
 * Settings form for the Bulk Course Studio embed activity.
 *
 * Like Edwiser's video activity, the teacher chooses a media source and
 * only the matching fields appear: a pasted link (YouTube, Vimeo, Google
 * Drive, OneDrive/SharePoint) or an uploaded video file. Supporting
 * resources can be attached either way. Moodle's standard sections
 * (restrict access, completion, tags, competencies) come from
 * standard_coursemodule_elements().
 *
 * @package    mod_bulkcoursestudio
 * @copyright  2026 Bulk Course Studio
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/course/moodleform_mod.php');

/**
 * Settings form for mod_bulkcoursestudio.
 *
 * @package    mod_bulkcoursestudio
 * @copyright  2026 Bulk Course Studio
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class mod_bulkcoursestudio_mod_form extends moodleform_mod {
    /** @var string[] Video file types accepted for upload. */
    const VIDEO_TYPES = ['.mp4', '.webm', '.m4v', '.mov', '.3gp'];

    /** @var int Largest uploaded video allowed: 100 MB. Longer videos should be linked instead. */
    const MAX_VIDEO_BYTES = 104857600;

    /**
     * Build the form fields.
     */
    public function definition() {
        $mform = $this->_form;

        $mform->addElement('header', 'mediaheader', get_string('embedsection', 'mod_bulkcoursestudio'));

        $mform->addElement('text', 'name', get_string('activityname', 'mod_bulkcoursestudio'), ['size' => '64']);
        $mform->setType('name', PARAM_TEXT);
        $mform->addRule('name', null, 'required', null, 'client');

        $mform->addElement('select', 'mediasource', get_string('mediasource', 'mod_bulkcoursestudio'), [
            'url' => get_string('mediasource:url', 'mod_bulkcoursestudio'),
            'upload' => get_string('mediasource:upload', 'mod_bulkcoursestudio'),
        ]);
        $mform->setDefault('mediasource', 'url');

        $mform->addElement('text', 'embedurl', get_string('embedurl', 'mod_bulkcoursestudio'), ['size' => '64']);
        $mform->setType('embedurl', PARAM_URL);
        $mform->addHelpButton('embedurl', 'embedurl', 'mod_bulkcoursestudio');
        $mform->hideIf('embedurl', 'mediasource', 'neq', 'url');

        $mform->addElement('static', 'embedurlnotice', '', get_string('embednotice:sharingrequired', 'mod_bulkcoursestudio'));
        $mform->hideIf('embedurlnotice', 'mediasource', 'neq', 'url');

        $maxmb = display_size(self::MAX_VIDEO_BYTES);
        $mform->addElement(
            'static',
            'uploadnotice',
            '',
            html_writer::div(get_string('mediasource:uploadinfo', 'mod_bulkcoursestudio', $maxmb))
            . html_writer::div('⚠️ ' . get_string('mediasource:uploadwarning', 'mod_bulkcoursestudio', $maxmb), 'text-danger')
        );
        $mform->hideIf('uploadnotice', 'mediasource', 'neq', 'upload');

        $mform->addElement('filemanager', 'mediafile', get_string('mediafile', 'mod_bulkcoursestudio'), null, [
            'subdirs' => 0,
            'maxfiles' => 1,
            'maxbytes' => self::MAX_VIDEO_BYTES,
            'accepted_types' => self::VIDEO_TYPES,
        ]);
        $mform->hideIf('mediafile', 'mediasource', 'neq', 'upload');

        $mform->addElement('header', 'overviewheader', get_string('overview', 'mod_bulkcoursestudio'));
        $this->standard_intro_elements(get_string('overview', 'mod_bulkcoursestudio'));

        $mform->addElement('header', 'resourcesheader', get_string('resourcesheader', 'mod_bulkcoursestudio'));
        $mform->addElement('static', 'resourcesdesc', '', get_string('resourcesdesc', 'mod_bulkcoursestudio'));
        $mform->addElement('filemanager', 'resources', get_string('resourcesfiles', 'mod_bulkcoursestudio'), null, [
            'subdirs' => 0,
            'maxfiles' => -1,
            'accepted_types' => '*',
        ]);

        $this->standard_coursemodule_elements();
        $this->add_action_buttons();
    }

    /**
     * Prepare the file manager draft areas (existing files when editing).
     *
     * @param array $defaultvalues
     */
    public function data_preprocessing(&$defaultvalues) {
        parent::data_preprocessing($defaultvalues);

        $contextid = null;
        if (!empty($this->current->coursemodule)) {
            $contextid = context_module::instance($this->current->coursemodule)->id;
        }
        foreach (['mediafile', 'resources'] as $area) {
            $draftitemid = file_get_submitted_draft_itemid($area);
            file_prepare_draft_area($draftitemid, $contextid, 'mod_bulkcoursestudio', $area, 0, ['subdirs' => 0]);
            $defaultvalues[$area] = $draftitemid;
        }

        if (empty($defaultvalues['mediasource'])) {
            $defaultvalues['mediasource'] = 'url';
        }
    }

    /**
     * Validate the submitted data: a link or a video file is required,
     * depending on the chosen source.
     *
     * @param array $data
     * @param array $files
     * @return array errors keyed by field name
     */
    public function validation($data, $files) {
        $errors = parent::validation($data, $files);

        if (($data['mediasource'] ?? 'url') === 'upload') {
            $info = file_get_draft_area_info($data['mediafile']);
            if (empty($info['filecount'])) {
                $errors['mediafile'] = get_string('mediafilerequired', 'mod_bulkcoursestudio');
            } else if (!empty($info['filesize']) && $info['filesize'] > self::MAX_VIDEO_BYTES) {
                // Server-side backstop: the file picker limit alone can be bypassed.
                $errors['mediafile'] = get_string(
                    'mediafiletoolarge',
                    'mod_bulkcoursestudio',
                    display_size(self::MAX_VIDEO_BYTES)
                );
            }
        } else if (empty($data['embedurl'])) {
            $errors['embedurl'] = get_string('required');
        } else {
            $provider = \mod_bulkcoursestudio\embed_resolver::detect_provider($data['embedurl']);
            if ($provider === '' ||
                    \mod_bulkcoursestudio\embed_resolver::to_embed_src($data['embedurl'], $provider) === '') {
                $errors['embedurl'] = get_string('embedunresolvable', 'mod_bulkcoursestudio');
            }
        }

        return $errors;
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
