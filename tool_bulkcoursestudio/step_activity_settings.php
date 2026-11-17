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
 * Native per-module settings screen for v1-scope activity types (spec §8:
 * assign, quiz, forum, page, url, resource, folder) — the promotion, asked
 * for explicitly, of step_activities.php's old shared generic form + JSON
 * fallback into each module's own real mod_form.php, the same form
 * core's own course/modedit.php would show for "Add an activity."
 *
 * This only works because build_manager::sync() is now called
 * *progressively* after every earlier wizard step (course details,
 * format, sections) — see its docblock — so by the time this page loads,
 * a real (hidden) course and real section numbering already exist. A
 * native mod_form genuinely needs both: many modules' forms reference
 * course-level settings (group mode, grading, availability) that only
 * make sense against a real course id, which is exactly the limitation
 * flagged in the previous build round as the reason this wasn't built
 * yet.
 *
 * Uses the same public entry points core/course/modedit.php itself uses
 * to build a module's settings form:
 * - can_add_moduleinfo($course, $modname, $sectionnum) for a new activity
 * - get_moduleinfo_data($cm, $course) for an existing one
 * both from course/modlib.php — never a hand-rolled substitute.
 *
 * HONEST CAVEAT: this has been written against course/modlib.php's
 * documented public contract, not verified against a live Moodle install
 * (none is available in this environment) — the exact shape of $data for
 * *add* mode (can_add_moduleinfo() returns only $module/$context/$cw, not
 * a ready-made $data; modedit.php itself builds that by hand) is
 * reconstructed here from Moodle's well-established, long-stable pattern.
 * Test this screen against a real Moodle 4.5+ instance for each v1-scope
 * module type before relying on it.
 *
 * @package    tool_bulkcoursestudio
 * @copyright  2026 Bulk Course Studio
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../../config.php');
require_once($CFG->libdir . '/adminlib.php');
require_once($CFG->dirroot . '/course/modlib.php');
require_once($CFG->dirroot . '/course/lib.php');

use tool_bulkcoursestudio\draft_repository;
use tool_bulkcoursestudio\build_manager;
use tool_bulkcoursestudio\native_form_registry;
use tool_bulkcoursestudio\output\ui;

$buildid = required_param('buildid', PARAM_INT);
$sectionid = required_param('sectionid', PARAM_INT);
$modname = required_param('modname', PARAM_PLUGIN);
$activityid = optional_param('activityid', 0, PARAM_INT);

admin_externalpage_setup('tool_bulkcoursestudio', '', null, '', ['pagelayout' => 'admin']);
require_capability('tool/bulkcoursestudio:manage', context_system::instance());

$build = draft_repository::get_build($buildid);

if (!native_form_registry::uses_native_form($modname)) {
    // Not a v1-scope type, or that module is missing/disabled on this
    // site after all — fall back to the generic form rather than a fatal.
    redirect(new moodle_url('/admin/tool/bulkcoursestudio/step_activities.php', [
        'buildid' => $buildid, 'sectionid' => $sectionid, 'activityid' => $activityid,
    ]));
}

if (empty($build->courseid)) {
    // Shouldn't happen in normal use — course details is always step 1,
    // and it syncs a real course immediately — but guard against a
    // directly-typed URL on a build that somehow skipped that step.
    redirect(
        new moodle_url('/admin/tool/bulkcoursestudio/step_coursedetails.php', ['buildid' => $buildid]),
        get_string('activitysettings:nocourseyet', 'tool_bulkcoursestudio'),
        null,
        \core\output\notification::NOTIFY_WARNING
    );
}

$course = get_course($build->courseid);
$sectionnum = draft_repository::get_section_number($buildid, $sectionid);

global $DB;
$draftactivity = $activityid
    ? $DB->get_record('tool_bulkcoursestudio_activity', ['id' => $activityid, 'sectionid' => $sectionid], '*', MUST_EXIST)
    : null;

$cm = null;
if ($draftactivity && !empty($draftactivity->committedcmid)) {
    $cm = get_coursemodule_from_id($modname, $draftactivity->committedcmid, $course->id, false, IGNORE_MISSING);
}

require_once(native_form_registry::mod_form_path($modname));
$mformclassname = 'mod_' . $modname . '_mod_form';

if ($cm) {
    // Editing an activity that's already real — the same helper
    // course/modedit.php itself calls, so $data arrives in exactly the
    // shape that module's own mod_form expects.
    [$cm, $context, $module, $data, $cw] = get_moduleinfo_data($cm, $course);
    $PAGE->set_cm($cm, $course);
} else {
    // Brand new activity — can_add_moduleinfo() only hands back
    // module/context/cw; modedit.php builds $data itself from there for
    // the "add" case, which this mirrors.
    [$module, $context, $cw] = can_add_moduleinfo($course, $modname, $sectionnum);
    $data = (object) [
        'section' => $cw->section,
        'visible' => $cw->visible,
        'course' => $course->id,
        'module' => $module->id,
        'modulename' => $module->name,
        'groupmode' => $course->groupmode,
        'groupingid' => $course->defaultgroupingid,
        'id' => '',
        'instance' => '',
        'coursemodule' => '',
        'add' => $modname,
        'update' => '',
        'return' => 0,
        'sr' => $sectionnum,
    ];
}

$PAGE->set_context($context);
$PAGE->set_url('/admin/tool/bulkcoursestudio/step_activity_settings.php', [
    'buildid' => $buildid, 'sectionid' => $sectionid, 'modname' => $modname, 'activityid' => $activityid,
]);
$moddisplayname = get_string('modulename', 'mod_' . $modname);
$PAGE->set_title(get_string('step:activities', 'tool_bulkcoursestudio') . ': ' . $moddisplayname);

$mform = new $mformclassname($data, $cw->section, $cm, $course);
$mform->set_data($data);

if ($mform->is_cancelled()) {
    redirect(new moodle_url('/admin/tool/bulkcoursestudio/step_activities.php', ['buildid' => $buildid]));
} else if ($formdata = $mform->get_data()) {
    // Every mod_form requires 'name' client- and server-side, so get_data()
    // only reaches here once it's set; the fallback is just defensive.
    $name = $formdata->name ?? $moddisplayname;

    $savedactivityid = draft_repository::add_or_update_activity(
        $sectionid,
        $activityid ?: null,
        $modname,
        $name,
        // Stored verbatim, exactly as core's own modedit.php would pass
        // $mform->get_data() straight to add_moduleinfo()/
        // update_moduleinfo() — including this module's own file/editor
        // draft-area references, which are still live in this same
        // request/session when sync() below consumes them.
        (array) $formdata,
        []
    );

    // Materialize this activity as a real course module immediately —
    // while the native form's own draft-area itemids (intro editor,
    // resource/folder's own 'files' field, etc.) are still valid for this
    // session, so that module's own add_instance()/update_instance() can
    // consume them itself, exactly as it would from a normal "Add an
    // activity" flow.
    build_manager::sync($buildid);

    redirect(new moodle_url('/admin/tool/bulkcoursestudio/step_activities.php', ['buildid' => $buildid]));
}

echo $OUTPUT->header();
echo ui::begin('activities', $buildid, $moddisplayname,
    get_string('activitysettings:sub', 'tool_bulkcoursestudio'));
$mform->display();
echo ui::end();
echo $OUTPUT->footer();
