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

namespace tool_bulkcoursestudio;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/course/lib.php');
require_once($CFG->dirroot . '/course/modlib.php');
require_once($CFG->libdir . '/completionlib.php');
require_once($CFG->libdir . '/filelib.php');

/**
 * Walks a draft build (course + sections + activities) and commits it using
 * only public Moodle course/module/completion/file APIs — never direct
 * writes to core tables. This is the single choke point both Flow A
 * (wizard) and Flow B (Excel) call into after the preview step, so the two
 * input paths can never diverge in how a course actually gets built.
 *
 * @package    tool_bulkcoursestudio
 * @copyright  2026 Bulk Course Studio
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class build_manager {
    /**
     * Validate a draft build without writing anything — powers the preview
     * step for both Flow A and Flow B.
     *
     * Deliberately read-only: every check here re-reads live site state
     * (installed formats, installed/visible activity types, category
     * existence) rather than trusting anything cached in the draft rows,
     * since the admin may be previewing a build that was started days ago
     * on a since-changed site.
     *
     * @param int $buildid
     * @return \stdClass {errors: string[], warnings: string[], tree: object}
     */
    public static function preview(int $buildid): \stdClass {
        global $DB;

        $build = $DB->get_record('tool_bulkcoursestudio_build', ['id' => $buildid], '*', MUST_EXIST);
        $coursedata = json_decode($build->coursedata, true) ?? [];
        $formatoptions = json_decode($build->formatoptions ?? '{}', true) ?? [];

        $result = (object) [
            'errors' => [],
            'warnings' => [],
            'tree' => (object) ['course' => $coursedata, 'sections' => []],
        ];

        // Course-level checks.
        if (empty($coursedata['fullname'])) {
            $result->errors[] = get_string('preview:error:nofullname', 'tool_bulkcoursestudio');
        }

        $categoryid = $coursedata['category'] ?? 0;
        if (empty($categoryid) || !$DB->record_exists('course_categories', ['id' => $categoryid])) {
            $result->errors[] = get_string('preview:error:badcategory', 'tool_bulkcoursestudio');
        }

        $installedformats = \core_component::get_plugin_list('format');
        $format = $coursedata['format'] ?? '';
        if ($format === '' || !isset($installedformats[$format])) {
            $result->errors[] = get_string('preview:error:badformat', 'tool_bulkcoursestudio', $format);
        }

        if (
            !empty($coursedata['startdate']) && !empty($coursedata['enddate'])
                && $coursedata['enddate'] <= $coursedata['startdate']
        ) {
            $result->errors[] = get_string('preview:error:enddatebeforestart', 'tool_bulkcoursestudio');
        }

        // Sections and activities.
        $sections = $DB->get_records('tool_bulkcoursestudio_section', ['buildid' => $buildid], 'sortorder ASC');
        $installedmods = \core_component::get_plugin_list('mod');

        foreach ($sections as $section) {
            $sectiontree = (object) [
                'id' => $section->id,
                'name' => $section->name,
                'activities' => [],
            ];

            $activities = $DB->get_records(
                'tool_bulkcoursestudio_activity',
                ['sectionid' => $section->id],
                'sortorder ASC'
            );

            foreach ($activities as $activity) {
                $activitytree = (object) [
                    'id' => $activity->id,
                    'modname' => $activity->modname,
                    'name' => $activity->name,
                ];

                if (!isset($installedmods[$activity->modname])) {
                    $result->errors[] = get_string(
                        'preview:error:unknownmod',
                        'tool_bulkcoursestudio',
                        (object) ['section' => $section->name, 'modname' => $activity->modname]
                    );
                } else if (!\core_plugin_manager::instance()->get_plugin_info('mod_' . $activity->modname)->is_enabled()) {
                    $result->warnings[] = get_string(
                        'preview:warning:disabledmod',
                        'tool_bulkcoursestudio',
                        (object) ['section' => $section->name, 'modname' => $activity->modname]
                    );
                }

                if ($activity->modname === 'bulkcoursestudio') {
                    $moduledata = json_decode($activity->moduledata, true) ?? [];
                    $url = $moduledata['embedurl'] ?? '';
                    $provider = class_exists('\\mod_bulkcoursestudio\\embed_resolver')
                        ? \mod_bulkcoursestudio\embed_resolver::detect_provider($url)
                        : '';
                    if ($provider === '') {
                        $result->errors[] = get_string(
                            'preview:error:badembedurl',
                            'tool_bulkcoursestudio',
                            (object) ['section' => $section->name, 'activity' => $activity->name]
                        );
                    }
                }

                if (in_array($activity->modname, ['resource', 'folder'], true)) {
                    $moduledata = json_decode($activity->moduledata, true) ?? [];
                    $expectedfile = trim((string) ($moduledata['localfilename'] ?? ''));
                    // A blank expected filename is fine to skip (admin may
                    // have uploaded directly via step_activities.php's own
                    // filemanager instead of going through Flow B's
                    // file-matching screen); only warn when a filename was
                    // promised in the sheet but nothing has been attached
                    // by either route.
                    if ($expectedfile !== '') {
                        $storedfiles = get_file_storage()->get_area_files(
                            \context_system::instance()->id,
                            'tool_bulkcoursestudio',
                            'activityfiles',
                            $activity->id,
                            'filename',
                            false
                        );
                        if (empty($storedfiles)) {
                            $result->warnings[] = get_string(
                                'preview:warning:missingfile',
                                'tool_bulkcoursestudio',
                                (object) ['section' => $section->name, 'activity' => $activity->name, 'filename' => $expectedfile]
                            );
                        }
                    }
                }

                $sectiontree->activities[] = $activitytree;
            }

            $result->tree->sections[] = $sectiontree;
        }

        if (empty($sections)) {
            $result->warnings[] = get_string('preview:warning:nosections', 'tool_bulkcoursestudio');
        }

        return $result;
    }

    /**
     * The final, validated commit used by step_commit.php's Save as Draft /
     * Publish Now / Schedule actions: re-validates via preview() and
     * refuses to write anything if that would have reported errors.
     *
     * This is deliberately a thin wrapper around sync() rather than where
     * the actual course/section/activity writes live — sync() is also
     * called *progressively*, after each individual wizard step (course
     * details, format, sections, each activity save), specifically so a
     * real (hidden) course and real course-module ids exist early enough
     * for step_activity_settings.php to embed each module's own native
     * mod_form, which needs a real $course and (when editing) a real $cm.
     * Gating every one of those progressive syncs on a full-build
     * preview() would block step 1 from ever creating the course at all —
     * format, for instance, genuinely isn't chosen until step 2 — so the
     * validated gate only applies here, at the one point the admin is
     * actually asking "is this build finished and ready."
     *
     * @param int $buildid
     * @return int the resulting course id
     * @throws \moodle_exception if preview() would have reported errors
     */
    public static function commit(int $buildid): int {
        $preview = self::preview($buildid);
        if (!empty($preview->errors)) {
            throw new \moodle_exception('committedwitherrors', 'tool_bulkcoursestudio', '', implode('; ', $preview->errors));
        }

        return self::sync($buildid);
    }

    /**
     * Idempotently brings the real (course, sections, activities) in sync
     * with the draft tables, via core APIs only — never a direct DB write
     * to a core table. Unlike commit(), this does NOT require preview() to
     * be error-free first, since it is also called progressively after
     * each wizard step, well before the build as a whole is complete or
     * valid. Always leaves the course hidden (visible = 0) the first time
     * it creates one; call publish() separately to flip it live.
     *
     * Safe to call repeatedly and out of a build's "natural" step order:
     * every write here (create_course()/update_course(),
     * course_create_sections_if_missing()/course_update_section(),
     * add_moduleinfo()/update_moduleinfo() via commit_activity()) is
     * idempotent against the draft rows' current content.
     *
     * @param int $buildid
     * @return int the resulting course id
     */
    public static function sync(int $buildid): int {
        global $DB;

        $build = $DB->get_record('tool_bulkcoursestudio_build', ['id' => $buildid], '*', MUST_EXIST);
        $coursedata = (object) (json_decode($build->coursedata, true) ?? []);
        $formatoptions = json_decode($build->formatoptions ?? '{}', true) ?? [];

        // Format isn't chosen until wizard step 2, but step 1 already needs
        // to create a real course — fall back to the site's conventional
        // default rather than leaving the field unset, same as core's own
        // create_course() form would if an admin left it untouched.
        if (empty($coursedata->format)) {
            $coursedata->format = 'topics';
        }

        $transaction = $DB->start_delegated_transaction();

        if (empty($build->courseid)) {
            $coursedata->visible = 0; // Publish/schedule flips this later via publish().
            $course = create_course($coursedata);
        } else {
            $course = get_course($build->courseid);
            $coursedata->id = $course->id;
            $coursedata->visible = $course->visible; // Don't disturb visibility on a re-commit/edit.
            update_course($coursedata);
            $course = get_course($course->id);
        }

        if (!empty($formatoptions)) {
            course_get_format($course)->update_course_format_options($formatoptions);
        }

        self::commit_course_image($course, $buildid);

        $sections = $DB->get_records('tool_bulkcoursestudio_section', ['buildid' => $buildid], 'sortorder ASC');

        // Make sure the course has at least as many sections as the draft
        // needs, in the course's own numbering (section 0 is the general
        // section and is never touched here).
        course_create_sections_if_missing($course, range(1, max(1, count($sections))));
        $modinfo = get_fast_modinfo($course);

        $sectionnum = 1;
        foreach ($sections as $section) {
            $sectiondata = (object) [
                'name' => $section->name,
                'summary' => $section->summary,
                'summaryformat' => FORMAT_HTML,
            ];
            course_update_section($course, $modinfo->get_section_info($sectionnum), $sectiondata);

            $activities = $DB->get_records(
                'tool_bulkcoursestudio_activity',
                ['sectionid' => $section->id],
                'sortorder ASC'
            );

            foreach ($activities as $activity) {
                self::commit_activity($course, $sectionnum, $activity);
            }

            $sectionnum++;
        }

        $DB->update_record('tool_bulkcoursestudio_build', (object) [
            'id' => $build->id,
            'courseid' => $course->id,
            'timemodified' => time(),
        ]);

        $transaction->allow_commit();

        return $course->id;
    }

    /**
     * Create or update one activity via add_moduleinfo()/update_moduleinfo()
     * — the same entry points Moodle's own "Add an activity" form uses, so
     * every module's own validation/side-effects (calendar events, grade
     * items, etc.) run exactly as they would for a manually-added activity.
     *
     * Tracks the resulting cmid back onto the draft row so re-committing an
     * already-built draft (editing a published course) updates the same
     * module instead of creating a duplicate.
     */
    private static function commit_activity(\stdClass $course, int $sectionnum, \stdClass $activity): void {
        global $DB;

        $moduledata = (object) (json_decode($activity->moduledata, true) ?? []);
        $moduledata->modulename = $activity->modname;
        $moduledata->course = $course->id;
        $moduledata->section = $sectionnum;
        $moduledata->name = $activity->name;

        $completiondata = json_decode($activity->completiondata ?? '{}', true) ?? [];
        foreach ($completiondata as $key => $value) {
            $moduledata->$key = $value;
        }

        $existingcmid = $DB->get_field('tool_bulkcoursestudio_activity', 'committedcmid', ['id' => $activity->id]);

        if (empty($existingcmid)) {
            $created = add_moduleinfo($moduledata, $course);
            $cmid = $created->coursemodule;
        } else {
            $cm = get_coursemodule_from_id('', $existingcmid, $course->id, false, IGNORE_MISSING);
            if (!$cm) {
                // The module was deleted out-of-band since this draft last
                // committed — fall back to creating it fresh rather than
                // failing the whole build.
                $created = add_moduleinfo($moduledata, $course);
                $cmid = $created->coursemodule;
            } else {
                $moduledata->coursemodule = $cm->id;
                $moduledata->instance = $cm->instance;
                update_moduleinfo($cm, $moduledata, $course);
                $cmid = $cm->id;
            }
        }

        $DB->set_field('tool_bulkcoursestudio_activity', 'committedcmid', $cmid, ['id' => $activity->id]);

        self::commit_activity_files($activity, $cmid);
    }

    /**
     * Copy any files the admin attached to a resource/folder-type activity
     * (stored in this plugin's own 'activityfiles' draft area, keyed by the
     * draft activity's id) into a fresh user/draft area, then hand that
     * draftitemid to the target module's own add_instance()/update_instance()
     * via update_moduleinfo()/add_moduleinfo() having already run above with
     * $moduledata->files set.
     *
     * This plugin never writes directly into another module's file area —
     * mod_resource/mod_folder's own instance-save logic calls
     * file_save_draft_area_files() itself once handed a draftitemid, exactly
     * as it would from a manually-submitted mod_form. Because that save has
     * already happened by the time this method runs (update_moduleinfo() ran
     * in commit_activity() before this call), this method instead re-runs a
     * second, targeted update for just the files field so the ordering never
     * has to be perfect: it is safe to call every commit, and a no-op when
     * there are no stored files for this activity.
     *
     * @param \stdClass $activity the draft activity row
     * @param int $cmid the course_module id just created/updated for it
     */
    private static function commit_activity_files(\stdClass $activity, int $cmid): void {
        if (!in_array($activity->modname, ['resource', 'folder'], true)) {
            // Only file-bearing module types need this; everything else
            // (embedded media, quizzes, forums, etc.) has no files field.
            return;
        }

        $fs = get_file_storage();
        $systemcontext = \context_system::instance();
        $storedfiles = $fs->get_area_files(
            $systemcontext->id,
            'tool_bulkcoursestudio',
            'activityfiles',
            $activity->id,
            'filename',
            false
        );

        if (empty($storedfiles)) {
            // Admin never attached files to this activity — leave whatever
            // the module already has untouched.
            return;
        }

        $cm = get_coursemodule_from_id($activity->modname, $cmid, 0, false, IGNORE_MISSING);
        if (!$cm) {
            return;
        }
        $modulecontext = \context_module::instance($cm->id);
        $filearea = $activity->modname === 'folder' ? 'content' : 'content';

        // Clear the module's existing files and copy the plugin's draft set
        // in directly — equivalent to re-submitting the module's own
        // filemanager field with this exact file set, but done without
        // round-tripping through a second draft area since we already hold
        // the final stored files.
        $fs->delete_area_files($modulecontext->id, 'mod_' . $activity->modname, $filearea, 0);
        foreach ($storedfiles as $storedfile) {
            if ($storedfile->is_directory()) {
                continue;
            }
            $fs->create_file_from_storedfile([
                'contextid' => $modulecontext->id,
                'component' => 'mod_' . $activity->modname,
                'filearea' => $filearea,
                'itemid' => 0,
                'filepath' => $storedfile->get_filepath(),
                'filename' => $storedfile->get_filename(),
            ], $storedfile);
        }

        if ($activity->modname === 'resource') {
            // Mod_resource caches the main file's display name/revision on
            // the instance row; bump revision so cached renderers refresh.
            global $DB;
            $DB->set_field('resource', 'revision', time(), ['id' => $cm->instance]);
        }
    }

    /**
     * Copy the course image the admin attached in step_coursedetails.php
     * (stored in this plugin's own 'courseimage' draft area, itemid =
     * buildid) into the course's own overview-files area, which is what
     * core actually reads for the course image shown in the course listing
     * and course-selector cards.
     *
     * Safe to call on every commit: it clears the course's existing
     * overviewfiles first, so re-committing a draft that now has no image
     * (admin removed it) correctly removes the old one, and re-committing
     * with the same image is a harmless no-op copy.
     *
     * @param \stdClass $course the course record just created/updated
     * @param int $buildid
     */
    private static function commit_course_image(\stdClass $course, int $buildid): void {
        $fs = get_file_storage();
        $systemcontext = \context_system::instance();

        $storedfiles = $fs->get_area_files(
            $systemcontext->id,
            'tool_bulkcoursestudio',
            'courseimage',
            $buildid,
            'filename',
            false
        );

        $coursecontext = \context_course::instance($course->id);
        $fs->delete_area_files($coursecontext->id, 'course', 'overviewfiles', 0);

        foreach ($storedfiles as $storedfile) {
            if ($storedfile->is_directory()) {
                continue;
            }
            $fs->create_file_from_storedfile([
                'contextid' => $coursecontext->id,
                'component' => 'course',
                'filearea' => 'overviewfiles',
                'itemid' => 0,
                'filepath' => $storedfile->get_filepath(),
                'filename' => $storedfile->get_filename(),
            ], $storedfile);
            // Only one overview image is supported per course by core; stop
            // after the first real file even if the admin attached more.
            break;
        }
    }

    /**
     * Promote a 'scheduled' (or freshly-committed draft, for "Publish now")
     * build to visible, via core's own course-visibility API rather than a
     * raw update to course.visible, so cache invalidation and event firing
     * happen exactly as they would for a manual "Show" in Moodle.
     *
     * @param int $buildid
     */
    public static function publish(int $buildid): void {
        global $DB;

        $build = $DB->get_record('tool_bulkcoursestudio_build', ['id' => $buildid], '*', MUST_EXIST);
        if (empty($build->courseid)) {
            throw new \coding_exception('Cannot publish a build that has not been committed yet');
        }

        $course = get_course($build->courseid);
        course_change_visibility($course->id, true);

        $DB->update_record('tool_bulkcoursestudio_build', (object) [
            'id' => $build->id,
            'status' => 'published',
            'timemodified' => time(),
        ]);
    }

    /**
     * Delete a section or activity from a *live* course, honoring the
     * admin's explicit choice between hide / delete-with-data / cancel.
     * See spec §3.3 — we only expose Moodle's own existing semantics here,
     * never a fourth custom behavior.
     *
     * @param string $target 'section'|'activity'
     * @param int $id sectionid (course_sections.id) or cmid, matching $target
     * @param string $action 'hide'|'delete'|'cancel'
     */
    public static function remove_with_choice(string $target, int $id, string $action): void {
        if ($action === 'cancel') {
            return;
        }

        if ($action === 'delete') {
            require_capability('tool/bulkcoursestudio:deletewithdata', \context_system::instance());
        }

        if ($target === 'activity') {
            if ($action === 'hide') {
                set_coursemodule_visible($id, 0);
            } else if ($action === 'delete') {
                course_delete_module($id);
            }
            return;
        }

        if ($target === 'section') {
            global $DB;
            $section = $DB->get_record('course_sections', ['id' => $id], '*', MUST_EXIST);
            $course = get_course($section->course);

            if ($action === 'hide') {
                course_update_section($course, $section, ['visible' => 0]);
            } else if ($action === 'delete') {
                course_delete_section($course, $section, true, true);
            }
            return;
        }

        throw new \coding_exception('Unknown remove_with_choice target: ' . $target);
    }

    /**
     * Discard an in-progress build entirely — index.php's "Discard" action.
     *
     * Since sync() now materializes a real (hidden) course from step 1
     * onward rather than only at final commit, an abandoned draft can
     * leave behind a real, if hidden, orphan course — unlike the old
     * pure-draft-table design, where "discard" was just deleting rows.
     * This soft-deletes that course (to the recycle bin, via core's own
     * delete_course(), never a raw DB delete) whenever the build was never
     * actually published, so discarding during the wizard doesn't litter
     * the course list with hidden leftovers. A *published* build's course
     * is never touched here — that's a real, live course the admin
     * deliberately published, and deleting it is a decision for normal
     * course management, not an accidental side effect of tidying up
     * drafts.
     *
     * @param int $buildid
     */
    public static function discard(int $buildid): void {
        $build = draft_repository::get_build($buildid);

        // Delete_course() itself checks no capability — the controller
        // calling discard() is responsible for require_capability() first,
        // same as every other method in this class.
        if (!empty($build->courseid) && $build->status !== 'published') {
            delete_course($build->courseid, false);
        }

        draft_repository::delete_build($buildid);
    }
}
