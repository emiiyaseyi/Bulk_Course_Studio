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
 * Discard an in-progress build (index.php's "Discard" action). Confirms
 * first, then calls build_manager::discard() — which also soft-deletes
 * the build's real (hidden) course, to the recycle bin, if it was never
 * published; see that method's docblock for why that's needed now that
 * build_manager::sync() materializes a real course from wizard step 1
 * onward rather than only at final commit.
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
$confirm = optional_param('confirm', 0, PARAM_BOOL);

admin_externalpage_setup('tool_bulkcoursestudio', '', null, '', ['pagelayout' => 'admin']);
require_capability('tool/bulkcoursestudio:manage', context_system::instance());

$build = draft_repository::get_build($buildid);
if ($build->usermodified != $USER->id) {
    throw new moodle_exception('nopermissions', 'error', '', get_string('pluginname', 'tool_bulkcoursestudio'));
}

$indexurl = new moodle_url('/admin/tool/bulkcoursestudio/index.php');
$PAGE->set_url('/admin/tool/bulkcoursestudio/discard_build.php', ['buildid' => $buildid]);
$PAGE->set_title(get_string('discardbuild', 'tool_bulkcoursestudio'));

if ($confirm && data_submitted()) {
    require_sesskey();
    build_manager::discard($buildid);
    redirect($indexurl, get_string('builddiscarded', 'tool_bulkcoursestudio'), null, \core\output\notification::NOTIFY_SUCCESS);
}

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('discardbuild', 'tool_bulkcoursestudio'));

$message = $build->status === 'published'
    ? get_string('discardbuild:confirmpublished', 'tool_bulkcoursestudio')
    : get_string('discardbuild:confirm', 'tool_bulkcoursestudio');

echo $OUTPUT->confirm(
    $message,
    new moodle_url(
        '/admin/tool/bulkcoursestudio/discard_build.php',
        ['buildid' => $buildid, 'confirm' => 1, 'sesskey' => sesskey()]
    ),
    $indexurl
);

echo $OUTPUT->footer();
