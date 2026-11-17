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

admin_externalpage_setup('tool_bulkcoursestudio', '', null, '', ['pagelayout' => 'admin']);
require_capability('tool/bulkcoursestudio:manage', context_system::instance());

$PAGE->set_url('/admin/tool/bulkcoursestudio/index.php');
$PAGE->set_title(get_string('pluginname', 'tool_bulkcoursestudio'));

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('pluginname', 'tool_bulkcoursestudio'));

echo html_writer::div(
    html_writer::link(
        new moodle_url('/admin/tool/bulkcoursestudio/overview.php'),
        get_string('overview:link', 'tool_bulkcoursestudio')
    ),
    'mb-3'
);

echo html_writer::start_div('d-flex', ['style' => 'gap: 0.5rem;']);
echo html_writer::link(
    new moodle_url('/admin/tool/bulkcoursestudio/step_coursedetails.php'),
    get_string('startnewbuild', 'tool_bulkcoursestudio'),
    ['class' => 'btn btn-primary']
);

if (has_capability('tool/bulkcoursestudio:bulkupload', context_system::instance())) {
    echo html_writer::link(
        new moodle_url('/admin/tool/bulkcoursestudio/excel_download.php'),
        get_string('downloadtemplate', 'tool_bulkcoursestudio'),
        ['class' => 'btn btn-secondary']
    );
    echo html_writer::link(
        new moodle_url('/admin/tool/bulkcoursestudio/excel_upload.php'),
        get_string('uploadtemplate', 'tool_bulkcoursestudio'),
        ['class' => 'btn btn-secondary']
    );
}
echo html_writer::link(
    new moodle_url('/admin/tool/bulkcoursestudio/edit_course.php'),
    get_string('editexisting', 'tool_bulkcoursestudio'),
    ['class' => 'btn btn-secondary']
);
echo html_writer::end_div();

echo $OUTPUT->heading(get_string('mydrafts', 'tool_bulkcoursestudio'), 3);

$builds = draft_repository::get_my_builds($USER->id);

if (empty($builds)) {
    echo $OUTPUT->notification(get_string('nodrafts', 'tool_bulkcoursestudio'), 'info');
} else {
    $table = new html_table();
    $table->head = [
        get_string('fullnamecourse'),
        get_string('status'),
        get_string('lastmodified'),
        '',
    ];

    foreach ($builds as $build) {
        $coursedata = json_decode($build->coursedata, true) ?? [];

        if ($build->status === 'scheduled') {
            $status = get_string('statusscheduled', 'tool_bulkcoursestudio', userdate($build->scheduledtime));
        } else if ($build->status === 'published') {
            $status = get_string('statuspublished', 'tool_bulkcoursestudio');
        } else {
            $status = get_string('statusdraft', 'tool_bulkcoursestudio');
        }

        // Re-opening the wizard is always safe here, published or not —
        // commit() never touches visibility, only publish() does — so
        // every status gets a direct edit link, not just in-progress drafts.
        $editurl = new moodle_url('/admin/tool/bulkcoursestudio/step_coursedetails.php', ['buildid' => $build->id]);
        $links = html_writer::link(
            $editurl,
            $build->status === 'published' || $build->status === 'scheduled'
                ? get_string('editexisting:editbutton', 'tool_bulkcoursestudio')
                : get_string('continuedraft', 'tool_bulkcoursestudio')
        );
        if (!empty($build->courseid)) {
            $links .= ' ' . html_writer::link(
                new moodle_url('/course/view.php', ['id' => $build->courseid]),
                get_string('view')
            );
        }
        $links .= ' ' . html_writer::link(
            new moodle_url('/admin/tool/bulkcoursestudio/discard_build.php', ['buildid' => $build->id]),
            get_string('discardbuild', 'tool_bulkcoursestudio')
        );

        $table->data[] = [
            format_string($coursedata['fullname'] ?? '(untitled)'),
            $status,
            userdate($build->timemodified),
            $links,
        ];
    }

    echo html_writer::table($table);
}

echo $OUTPUT->footer();
