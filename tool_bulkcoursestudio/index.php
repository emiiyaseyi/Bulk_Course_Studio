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
 * Bulk Course Studio dashboard — entry point for Flow A (wizard) and
 * Flow B (Excel template), and the Drafts/Scheduled list.
 *
 * @package    tool_bulkcoursestudio
 * @copyright  2026 Bulk Course Studio
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../../config.php');
require_once($CFG->libdir . '/adminlib.php');

use tool_bulkcoursestudio\draft_repository;
use tool_bulkcoursestudio\output\ui;

admin_externalpage_setup('tool_bulkcoursestudio', '', null, '', ['pagelayout' => 'admin']);
require_capability('tool/bulkcoursestudio:manage', context_system::instance());

$PAGE->set_url('/admin/tool/bulkcoursestudio/index.php');
$PAGE->set_title(get_string('pluginname', 'tool_bulkcoursestudio'));

echo $OUTPUT->header();

echo ui::begin('', 0, get_string('pluginname', 'tool_bulkcoursestudio'), get_string('dashboard:sub', 'tool_bulkcoursestudio'));

$cards = [];
$cards[] = ['/admin/tool/bulkcoursestudio/step_coursedetails.php', '＋', 'startnewbuild', 'startnewbuild:desc', true];
if (has_capability('tool/bulkcoursestudio:bulkupload', context_system::instance())) {
    $cards[] = ['/admin/tool/bulkcoursestudio/excel_download.php', '⬇', 'downloadtemplate', 'downloadtemplate:desc', false];
    $cards[] = ['/admin/tool/bulkcoursestudio/excel_upload.php', '⬆', 'uploadtemplate', 'uploadtemplate:desc', false];
}
$cards[] = ['/admin/tool/bulkcoursestudio/edit_course.php', '✎', 'editexisting', 'editexisting:desc', false];

echo html_writer::start_div('bcs-startgrid');
foreach ($cards as [$path, $glyph, $titlekey, $desckey, $primary]) {
    echo html_writer::link(
        new moodle_url($path),
        html_writer::div($glyph, 'bcs-startcard-icon')
        . html_writer::div(get_string($titlekey, 'tool_bulkcoursestudio'), 'bcs-startcard-title')
        . html_writer::div(get_string($desckey, 'tool_bulkcoursestudio'), 'bcs-startcard-text'),
        ['class' => 'bcs-startcard' . ($primary ? ' is-primary' : '')]
    );
}
echo html_writer::end_div();

echo html_writer::tag('h3', get_string('mydrafts', 'tool_bulkcoursestudio'), ['class' => 'bcs-subhead']);

$builds = draft_repository::get_my_builds($USER->id);

if (empty($builds)) {
    echo html_writer::div(get_string('nodrafts', 'tool_bulkcoursestudio'), 'bcs-note');
} else {
    echo html_writer::start_div('bcs-section');
    foreach ($builds as $build) {
        $coursedata = json_decode($build->coursedata, true) ?? [];

        if ($build->status === 'scheduled') {
            $status = get_string('statusscheduled', 'tool_bulkcoursestudio', userdate($build->scheduledtime));
        } else if ($build->status === 'published') {
            $status = get_string('statuspublished', 'tool_bulkcoursestudio');
        } else {
            $status = get_string('statusdraft', 'tool_bulkcoursestudio');
        }

        // Re-opening the wizard is always safe here, published or not:
        // commit() never touches visibility, only publish() does.
        $editurl = new moodle_url('/admin/tool/bulkcoursestudio/step_coursedetails.php', ['buildid' => $build->id]);
        $actions = html_writer::link(
            $editurl,
            $build->status === 'published' || $build->status === 'scheduled'
                ? get_string('editexisting:editbutton', 'tool_bulkcoursestudio')
                : get_string('continuedraft', 'tool_bulkcoursestudio'),
            ['class' => 'btn btn-primary btn-sm']
        );
        if (!empty($build->courseid)) {
            $actions .= ' ' . html_writer::link(
                new moodle_url('/course/view.php', ['id' => $build->courseid]),
                get_string('view'),
                ['class' => 'btn btn-secondary btn-sm']
            );
        }
        $actions .= ' ' . html_writer::link(
            new moodle_url('/admin/tool/bulkcoursestudio/discard_build.php', ['buildid' => $build->id]),
            get_string('discardbuild', 'tool_bulkcoursestudio'),
            ['class' => 'btn btn-outline-danger btn-sm']
        );

        echo html_writer::div(
            html_writer::span(
                format_string($coursedata['fullname'] ?? '(untitled)')
                . html_writer::span(s($status), 'bcs-badge')
                . html_writer::div(
                    get_string('lastmodified') . ': ' . userdate($build->timemodified),
                    'text-muted small fw-normal font-weight-normal'
                ),
                'bcs-activity-name'
            ) . html_writer::span($actions),
            'bcs-activity'
        );
    }
    echo html_writer::end_div();
}

echo html_writer::div(
    html_writer::link(
        new moodle_url('/admin/tool/bulkcoursestudio/overview.php'),
        get_string('overview:link', 'tool_bulkcoursestudio')
    ),
    'mt-3'
);

echo ui::end();
echo $OUTPUT->footer();
