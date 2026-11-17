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
 * Library of functions for mod_bulkcoursestudio — the required/standard
 * module callbacks every activity type implements.
 *
 * @package    mod_bulkcoursestudio
 * @copyright  2026 Bulk Course Studio
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/completionlib.php');

/**
 * Declares which core features this module supports.
 */
function bulkcoursestudio_supports(string $feature) {
    switch ($feature) {
        case FEATURE_MOD_INTRO:
            return true;
        case FEATURE_SHOW_DESCRIPTION:
            return true;
        case FEATURE_COMPLETION_TRACKS_VIEWS:
            return true;
        case FEATURE_COMPLETION_HAS_RULES:
            return true;
        case FEATURE_BACKUP_MOODLE2:
            return true;
        case FEATURE_MOD_PURPOSE:
            // MOD_PURPOSE_CONTENT matches this module's role (delivering
            // embedded media), consistent with how Resource/Page classify
            // themselves.
            return defined('MOD_PURPOSE_CONTENT') ? MOD_PURPOSE_CONTENT : null;
        default:
            return null;
    }
}

/**
 * Add a new bulkcoursestudio instance. Called by the core add_moduleinfo()
 * path — including when tool_bulkcoursestudio's build_manager commits a
 * draft activity of this type, so no bespoke insert path is needed.
 */
function bulkcoursestudio_add_instance(stdClass $data, $mform = null): int {
    global $DB;

    $data->timecreated = time();
    $data->timemodified = $data->timecreated;
    bulkcoursestudio_prepare_media_fields($data);

    $data->id = $DB->insert_record('bulkcoursestudio', $data);
    bulkcoursestudio_save_files($data);

    return $data->id;
}

/**
 * Normalise the media source fields before saving: an uploaded video has
 * no link, a linked video has its provider detected from the URL.
 *
 * @param stdClass $data form data being saved
 */
function bulkcoursestudio_prepare_media_fields(stdClass $data): void {
    $source = $data->mediasource ?? 'url';
    if ($source === 'upload') {
        $data->mediasource = 'upload';
        $data->embedurl = '';
        $data->provider = 'upload';
    } else if ($source === 'import') {
        // Copy a video in from a public link: after the copy it is a normal
        // uploaded video (see bulkcoursestudio_save_files()).
        $data->importurl = \mod_bulkcoursestudio\embed_resolver::extract_url($data->embedurl ?? '');
        $data->mediasource = 'upload';
        $data->embedurl = '';
        $data->provider = 'upload';
    } else if ($source === 'direct') {
        $data->mediasource = 'direct';
        $data->embedurl = \mod_bulkcoursestudio\embed_resolver::extract_url($data->embedurl ?? '');
        $data->provider = 'direct';
    } else {
        $data->mediasource = 'url';
        $data->embedurl = \mod_bulkcoursestudio\embed_resolver::extract_url($data->embedurl ?? '');
        $data->provider = \mod_bulkcoursestudio\embed_resolver::detect_provider($data->embedurl);
    }
    $data->completionpercent = (int) ($data->completionpercent ?? 0);
    $data->completiontimespent = (int) ($data->completiontimespent ?? 0);
    $data->autonext = empty($data->autonext) ? 0 : 1;
    $data->displaymode = (($data->displaymode ?? 'inline') === 'popup') ? 'popup' : 'inline';
}

/**
 * Move the uploaded video and resource files from the form's draft areas
 * into this activity's own file areas.
 *
 * @param stdClass $data form data; needs ->coursemodule
 */
function bulkcoursestudio_save_files(stdClass $data): void {
    if (empty($data->coursemodule)) {
        return;
    }
    $context = context_module::instance($data->coursemodule);

    $importing = !empty($data->importurl);
    if ($importing) {
        \mod_bulkcoursestudio\video_importer::fetch($data->importurl, $context);
    }

    if (isset($data->mediafile) && !$importing) {
        if ($data->mediasource === 'upload') {
            file_save_draft_area_files(
                $data->mediafile,
                $context->id,
                'mod_bulkcoursestudio',
                'mediafile',
                0,
                ['subdirs' => 0, 'maxfiles' => 1]
            );
        } else {
            // Switched to a pasted link: don't keep an orphaned video file.
            get_file_storage()->delete_area_files($context->id, 'mod_bulkcoursestudio', 'mediafile', 0);
        }
    }
    if (isset($data->transcript)) {
        file_save_draft_area_files(
            $data->transcript,
            $context->id,
            'mod_bulkcoursestudio',
            'transcript',
            0,
            ['subdirs' => 0, 'maxfiles' => 1]
        );
    }
    if (isset($data->resources)) {
        file_save_draft_area_files(
            $data->resources,
            $context->id,
            'mod_bulkcoursestudio',
            'resources',
            0,
            ['subdirs' => 0, 'maxfiles' => -1]
        );
    }
}

/**
 * Update an existing bulkcoursestudio instance.
 */
function bulkcoursestudio_update_instance(stdClass $data, $mform = null): bool {
    global $DB;

    $data->id = $data->instance;
    $data->timemodified = time();
    bulkcoursestudio_prepare_media_fields($data);

    $result = $DB->update_record('bulkcoursestudio', $data);
    bulkcoursestudio_save_files($data);

    return $result;
}

/**
 * Delete a bulkcoursestudio instance and its progress rows.
 */
function bulkcoursestudio_delete_instance(int $id): bool {
    global $DB;

    $DB->delete_records('bulkcoursestudio_progress', ['bulkcoursestudioid' => $id]);
    $DB->delete_records('bulkcoursestudio', ['id' => $id]);

    return true;
}

/**
 * Course reset support: clears per-user watch progress (and the
 * completion state it drove) when an admin resets a course for reuse,
 * same as a quiz clears attempts or a forum clears posts. The instance
 * rows themselves (name, embed URL, settings) are never touched by reset
 * — only user-generated data is.
 *
 * @param stdClass $data reset-course form data; must include ->courseid
 * @return array a single-row status array, as core's reset UI expects
 */
function bulkcoursestudio_reset_userdata(stdClass $data): array {
    global $DB;

    $status = [];

    if (!empty($data->reset_bulkcoursestudio_progress)) {
        $sql = "SELECT bcs.id
                  FROM {bulkcoursestudio} bcs
                 WHERE bcs.course = :courseid";
        $instanceids = $DB->get_fieldset_sql($sql, ['courseid' => $data->courseid]);

        if (!empty($instanceids)) {
            [$insql, $params] = $DB->get_in_or_equal($instanceids, SQL_PARAMS_NAMED);
            $DB->delete_records_select('bulkcoursestudio_progress', "bulkcoursestudioid $insql", $params);
        }

        $status[] = [
            'component' => get_string('modulenameplural', 'mod_bulkcoursestudio'),
            'item' => get_string('reset:progress', 'mod_bulkcoursestudio'),
            'error' => false,
        ];
    }

    return $status;
}

/**
 * Adds the "Delete all watch-progress data" checkbox to the standard
 * course-reset form, under this module's own section.
 */
function bulkcoursestudio_reset_course_form_definition(&$mform): void {
    $mform->addElement('header', 'bulkcoursestudiosheader', get_string('modulenameplural', 'mod_bulkcoursestudio'));
    $mform->addElement(
        'checkbox',
        'reset_bulkcoursestudio_progress',
        get_string('reset:progress', 'mod_bulkcoursestudio')
    );
}

/**
 * Default values for this module's "Reset course" checkboxes.
 *
 * @param stdClass $course
 * @return array
 */
function bulkcoursestudio_reset_course_form_defaults(stdClass $course): array {
    return ['reset_bulkcoursestudio_progress' => 1];
}

/**
 * Extra course-page info for the activity, including the custom completion
 * rule so Moodle's completion system can read it.
 *
 * @param stdClass $coursemodule
 * @return cached_cm_info|false
 */
function bulkcoursestudio_get_coursemodule_info($coursemodule) {
    global $DB;

    $instance = $DB->get_record(
        'bulkcoursestudio',
        ['id' => $coursemodule->instance],
        'id, name, intro, introformat, completionpercent, completiontimespent'
    );
    if (!$instance) {
        return false;
    }

    $info = new cached_cm_info();
    $info->name = $instance->name;
    if ($coursemodule->showdescription) {
        $info->content = format_module_intro('bulkcoursestudio', $instance, $coursemodule->id, false);
    }
    if ($coursemodule->completion == COMPLETION_TRACKING_AUTOMATIC) {
        $info->customdata['customcompletionrules']['completionpercent'] = (int) $instance->completionpercent;
        $info->customdata['customcompletionrules']['completiontimespent'] = (int) $instance->completiontimespent;
    }
    return $info;
}

/**
 * Serve a file embedded in this module's own "intro" text (via the
 * standard intro editor's file-embedding support) — required so an image
 * a teacher pastes/embeds into the activity description actually loads
 * for students, rather than 404ing, since Moodle has no generic default
 * handler for a module's own file areas; each module must implement this
 * itself. No other file area is served here: this module has no other
 * module-owned files (uploaded course content lives in this plugin's own
 * tool_bulkcoursestudio draft areas until commit, then in the target
 * activity's own component, never in mod_bulkcoursestudio's).
 *
 * @param stdClass $course
 * @param stdClass $cm
 * @param context $context
 * @param string $filearea
 * @param array $args
 * @param bool $forcedownload
 * @param array $options
 * @return bool false to signal "not found/not served" on any mismatch
 */
function bulkcoursestudio_pluginfile(
    $course,
    $cm,
    $context,
    string $filearea,
    array $args,
    bool $forcedownload,
    array $options = []
) {
    if ($context->contextlevel != CONTEXT_MODULE) {
        return false;
    }

    require_login($course, true, $cm);
    require_capability('mod/bulkcoursestudio:view', $context);

    if (!in_array($filearea, ['intro', 'mediafile', 'resources'], true)) {
        return false;
    }

    $itemid = (int) array_shift($args);
    if ($itemid !== 0) {
        return false;
    }

    $fs = get_file_storage();
    $relativepath = implode('/', $args);
    $fullpath = "/$context->id/mod_bulkcoursestudio/$filearea/0/$relativepath";
    $file = $fs->get_file_by_hash(sha1($fullpath));
    if (!$file || $file->is_directory()) {
        return false;
    }

    send_stored_file($file, null, 0, $forcedownload, $options);
    return true;
}
