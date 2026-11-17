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
 * The teacher chooses a media source and only the matching fields appear:
 * a pasted link (YouTube, Vimeo, Loom, Google Drive, OneDrive/SharePoint),
 * a direct link to a video file, or an uploaded video file. Resources and
 * a transcript can be attached either way. Moodle's standard sections
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
            'direct' => get_string('mediasource:direct', 'mod_bulkcoursestudio'),
            'upload' => get_string('mediasource:upload', 'mod_bulkcoursestudio'),
        ]);
        $mform->setDefault('mediasource', 'url');

        $mform->addElement('static', 'urlnotice', '', get_string('mediasource:urlinfo', 'mod_bulkcoursestudio'));
        $mform->hideIf('urlnotice', 'mediasource', 'neq', 'url');
        $mform->addElement('static', 'directnotice', '', get_string('mediasource:directinfo', 'mod_bulkcoursestudio'));
        $mform->hideIf('directnotice', 'mediasource', 'neq', 'direct');

        $mform->addElement('text', 'embedurl', get_string('embedurl', 'mod_bulkcoursestudio'), ['size' => '64']);
        $mform->setType('embedurl', PARAM_URL);
        $mform->addHelpButton('embedurl', 'embedurl', 'mod_bulkcoursestudio');
        $mform->hideIf('embedurl', 'mediasource', 'eq', 'upload');

        $mform->addElement('static', 'sharingnotice', '', get_string('embednotice:sharingrequired', 'mod_bulkcoursestudio'));
        $mform->hideIf('sharingnotice', 'mediasource', 'neq', 'url');

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

        $mform->addElement('advcheckbox', 'autonext', get_string('autonext', 'mod_bulkcoursestudio'));
        $mform->addHelpButton('autonext', 'autonext', 'mod_bulkcoursestudio');
        $mform->setDefault('autonext', 1);

        $mform->addElement('header', 'overviewheader', get_string('overview', 'mod_bulkcoursestudio'));
        $this->standard_intro_elements(get_string('overview', 'mod_bulkcoursestudio'));

        $mform->addElement('header', 'resourcesheader', get_string('resourcesheader', 'mod_bulkcoursestudio'));
        $mform->addElement('static', 'resourcesdesc', '', get_string('resourcesdesc', 'mod_bulkcoursestudio'));
        $mform->addElement('filemanager', 'resources', get_string('resourcesfiles', 'mod_bulkcoursestudio'), null, [
            'subdirs' => 0,
            'maxfiles' => -1,
            'accepted_types' => '*',
        ]);

        $mform->addElement('header', 'transcriptheader', get_string('transcriptheader', 'mod_bulkcoursestudio'));
        $mform->addElement('static', 'transcriptdesc', '', get_string('transcriptdesc', 'mod_bulkcoursestudio'));
        $mform->addElement('filemanager', 'transcript', get_string('transcriptfile', 'mod_bulkcoursestudio'), null, [
            'subdirs' => 0,
            'maxfiles' => 1,
            'accepted_types' => ['.txt', '.vtt', '.srt'],
        ]);

        $this->standard_coursemodule_elements();
        $this->add_action_buttons();
    }

    /**
     * Prepare the file manager draft areas (existing files when editing)
     * and the completion rule's checkbox.
     *
     * @param array $defaultvalues
     */
    public function data_preprocessing(&$defaultvalues) {
        parent::data_preprocessing($defaultvalues);

        $contextid = null;
        if (!empty($this->current->coursemodule)) {
            $contextid = context_module::instance($this->current->coursemodule)->id;
        }
        foreach (['mediafile', 'resources', 'transcript'] as $area) {
            $draftitemid = file_get_submitted_draft_itemid($area);
            file_prepare_draft_area($draftitemid, $contextid, 'mod_bulkcoursestudio', $area, 0, ['subdirs' => 0]);
            $defaultvalues[$area] = $draftitemid;
        }

        if (empty($defaultvalues['mediasource'])) {
            $defaultvalues['mediasource'] = 'url';
        }

        $suffix = $this->get_suffix();
        $percent = (int) ($defaultvalues['completionpercent'] ?? 0);
        $defaultvalues['completionpercentenabled' . $suffix] = $percent > 0 ? 1 : 0;
        $defaultvalues['completionpercent' . $suffix] = $percent > 0 ? $percent : 80;
    }

    /**
     * Clear the percentage when the rule is switched off.
     *
     * @param stdClass $data
     */
    public function data_postprocessing($data) {
        parent::data_postprocessing($data);

        if (!empty($data->completionunlocked)) {
            $suffix = $this->get_suffix();
            $completion = $data->{'completion' . $suffix} ?? ($data->completion ?? 0);
            $automatic = !empty($completion) && $completion == COMPLETION_TRACKING_AUTOMATIC;
            $enabled = !empty($data->{'completionpercentenabled' . $suffix});
            if (!$enabled || !$automatic) {
                $data->{'completionpercent' . $suffix} = 0;
            }
        }
    }

    /**
     * Validate the submitted data.
     *
     * @param array $data
     * @param array $files
     * @return array errors keyed by field name
     */
    public function validation($data, $files) {
        $errors = parent::validation($data, $files);
        $suffix = $this->get_suffix();
        $source = $data['mediasource'] ?? 'url';
        $provider = $source;

        if ($source === 'upload') {
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
        } else if ($source === 'direct') {
            if (!\mod_bulkcoursestudio\embed_resolver::is_direct_video_url($data['embedurl'])) {
                $errors['embedurl'] = get_string('directurlinvalid', 'mod_bulkcoursestudio');
            }
        } else {
            $provider = \mod_bulkcoursestudio\embed_resolver::detect_provider($data['embedurl']);
            if ($provider === '' ||
                    \mod_bulkcoursestudio\embed_resolver::to_embed_src($data['embedurl'], $provider) === '') {
                $errors['embedurl'] = get_string('embedunresolvable', 'mod_bulkcoursestudio');
            }
        }

        // Percentage completion: a number from 1 to 100, and only for
        // sources whose playback position can be read.
        if (!empty($data['completionpercentenabled' . $suffix])) {
            $percent = (int) ($data['completionpercent' . $suffix] ?? 0);
            $group = 'completionpercentgroup' . $suffix;
            if ($percent < 1 || $percent > 100) {
                $errors[$group] = get_string('completionpercent_error', 'mod_bulkcoursestudio');
            } else if (!isset($errors['embedurl']) && !\mod_bulkcoursestudio\embed_resolver::supports_progress($provider)) {
                $errors[$group] = get_string('completionpercent_unsupported', 'mod_bulkcoursestudio');
            }
        }

        return $errors;
    }

    /**
     * Custom completion rule: watch at least a percentage of the video.
     *
     * @return string[] names of the added rule elements
     */
    public function add_completion_rules() {
        $mform = $this->_form;
        $suffix = $this->get_suffix();

        $group = [
            $mform->createElement(
                'checkbox',
                'completionpercentenabled' . $suffix,
                '',
                get_string('completionpercent', 'mod_bulkcoursestudio')
            ),
            $mform->createElement('text', 'completionpercent' . $suffix, '', ['size' => 3]),
            $mform->createElement('static', 'completionpercentsign' . $suffix, '', '%'),
        ];
        $mform->addGroup(
            $group,
            'completionpercentgroup' . $suffix,
            get_string('completionpercentgroup', 'mod_bulkcoursestudio'),
            [' '],
            false
        );
        $mform->setType('completionpercent' . $suffix, PARAM_INT);
        $mform->setDefault('completionpercent' . $suffix, 80);
        $mform->hideIf('completionpercent' . $suffix, 'completionpercentenabled' . $suffix, 'notchecked');
        $mform->addHelpButton('completionpercentgroup' . $suffix, 'completionpercentgroup', 'mod_bulkcoursestudio');

        return ['completionpercentgroup' . $suffix];
    }

    /**
     * Whether the percentage rule is switched on in the submitted data.
     *
     * @param array $data
     * @return bool
     */
    public function completion_rule_enabled($data) {
        $suffix = $this->get_suffix();
        return !empty($data['completionpercentenabled' . $suffix]) && (int) ($data['completionpercent' . $suffix] ?? 0) > 0;
    }
}
