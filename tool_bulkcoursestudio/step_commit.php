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
 * Wizard step 6: commit (spec §3.1 step 7 — Save as Draft / Publish now /
 * Schedule). All three modes call build_manager::commit() first; only
 * "publish" additionally calls publish() immediately, and "schedule"
 * instead records a future scheduledtime for the cron task
 * (classes/task/publish_scheduled.php) to act on.
 *
 * @package    tool_bulkcoursestudio
 * @copyright  2026 Bulk Course Studio
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../../config.php');
require_once($CFG->libdir . '/adminlib.php');

use tool_bulkcoursestudio\draft_repository;
use tool_bulkcoursestudio\build_manager;

$buildid = required_param('buildid', PARAM_INT);
$mode = required_param('mode', PARAM_ALPHA); // Draft, publish, or schedule.

admin_externalpage_setup('tool_bulkcoursestudio', '', null, '', ['pagelayout' => 'admin']);
require_capability('tool/bulkcoursestudio:manage', context_system::instance());

draft_repository::get_build($buildid);
$PAGE->set_url('/admin/tool/bulkcoursestudio/step_commit.php', ['buildid' => $buildid, 'mode' => $mode]);
$PAGE->set_title(get_string('step:commit', 'tool_bulkcoursestudio'));

// The "schedule" mode needs a date picked before anything is committed —
// show that small form first (GET), then commit on its own submit (POST;
// going through a moodleform would be more idiomatic, kept as a plain
// form here to avoid a fourth form class for one date field).
if ($mode === 'schedule' && !optional_param('scheduledtimestr', '', PARAM_RAW)) {
    echo $OUTPUT->header();
    echo $OUTPUT->heading(get_string('schedulelater', 'tool_bulkcoursestudio'));
    echo html_writer::start_tag('form', ['method' => 'post', 'action' => $PAGE->url->out(false)]);
    echo html_writer::input_hidden_params($PAGE->url);
    echo html_writer::input_hidden_params(new moodle_url('', ['sesskey' => sesskey()]));
    echo html_writer::empty_tag('input', [
        'type' => 'datetime-local', 'name' => 'scheduledtimestr', 'required' => 'required',
    ]);
    echo html_writer::empty_tag('input', [
        'type' => 'submit', 'value' => get_string('schedulelater', 'tool_bulkcoursestudio'), 'class' => 'btn btn-primary',
    ]);
    echo html_writer::end_tag('form');
    echo $OUTPUT->footer();
    exit;
}

require_sesskey();

$preview = build_manager::preview($buildid);
if (!empty($preview->errors)) {
    redirect(
        new moodle_url('/admin/tool/bulkcoursestudio/step_preview.php', ['buildid' => $buildid]),
        get_string('fixerrorsfirst', 'tool_bulkcoursestudio'),
        null,
        \core\output\notification::NOTIFY_ERROR
    );
}

$courseid = build_manager::commit($buildid);

global $DB;

if ($mode === 'publish') {
    require_capability('tool/bulkcoursestudio:schedule', context_system::instance());
    build_manager::publish($buildid);
    $message = get_string('published', 'tool_bulkcoursestudio');
} else if ($mode === 'schedule') {
    require_capability('tool/bulkcoursestudio:schedule', context_system::instance());
    $scheduledtimestr = required_param('scheduledtimestr', PARAM_RAW);
    $scheduledtime = strtotime($scheduledtimestr);
    if (!$scheduledtime || $scheduledtime <= time()) {
        throw new moodle_exception('invaliddata', 'error', '', get_string('schedulelater', 'tool_bulkcoursestudio'));
    }
    $DB->update_record('tool_bulkcoursestudio_build', (object) [
        'id' => $buildid,
        'status' => 'scheduled',
        'scheduledtime' => $scheduledtime,
        'timemodified' => time(),
    ]);
    $message = get_string('scheduled', 'tool_bulkcoursestudio', userdate($scheduledtime));
} else {
    $message = get_string('saveddraft', 'tool_bulkcoursestudio');
}

redirect(
    new moodle_url('/admin/tool/bulkcoursestudio/index.php'),
    $message,
    null,
    \core\output\notification::NOTIFY_SUCCESS
);
