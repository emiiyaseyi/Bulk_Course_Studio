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
 * Feature overview for Bulk Course Studio: a hero banner, the main
 * features, quick links to every screen, and who built the plugin.
 *
 * Only a navigation aid: it reads no plugin data and writes nothing, so it
 * is safe to leave linked from the admin tree permanently.
 *
 * @package    tool_bulkcoursestudio
 * @copyright  2026 Bulk Course Studio
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../../config.php');
require_once($CFG->libdir . '/adminlib.php');
require_once($CFG->dirroot . '/course/lib.php');

admin_externalpage_setup('tool_bulkcoursestudio_overview', '', null, '', ['pagelayout' => 'admin']);
$systemcontext = context_system::instance();
require_capability('tool/bulkcoursestudio:manage', $systemcontext);

$PAGE->set_url('/admin/tool/bulkcoursestudio/overview.php');
$PAGE->set_title(get_string('overview:title', 'tool_bulkcoursestudio'));

$plugin = core_plugin_manager::instance()->get_plugin_info('tool_bulkcoursestudio');
$release = $plugin ? $plugin->release : '';
$modcount = count(get_module_types_names());
$canupload = has_capability('tool/bulkcoursestudio:bulkupload', $systemcontext);
$base = '/admin/tool/bulkcoursestudio/';
$str = function (string $key, $a = null): string {
    return get_string($key, 'tool_bulkcoursestudio', $a);
};

echo $OUTPUT->header();
echo html_writer::start_div('bcs bcs-overview');
echo html_writer::link(
    new moodle_url($base . 'index.php'),
    '← ' . $str('back'),
    ['class' => 'bcs-back']
);

// Hero banner.
echo html_writer::start_div('bcs-ov-hero');
echo html_writer::span('🎓 ' . $str('ov:badge', $release), 'bcs-ov-pill');
echo html_writer::tag('h1', $str('pluginname'), ['class' => 'bcs-ov-title']);
echo html_writer::tag('p', $str('ov:tagline'), ['class' => 'bcs-ov-tagline']);
echo html_writer::start_div('bcs-ov-buttons');
echo html_writer::link(new moodle_url($base . 'index.php'), '📊 ' . $str('ov:btn:dashboard'), ['class' => 'bcs-ov-btn is-light']);
echo html_writer::link(new moodle_url($base . 'step_coursedetails.php'), '＋ ' . $str('startnewbuild'), ['class' => 'bcs-ov-btn']);
if ($canupload) {
    echo html_writer::link(new moodle_url($base . 'excel_download.php'), '📥 ' . $str('downloadtemplate'), ['class' => 'bcs-ov-btn']);
}
echo html_writer::end_div();

$stats = [
    ['5', $str('ov:stat:steps')],
    [(string) $modcount, $str('ov:stat:activitytypes')],
    [$release !== '' ? $release : '-', $str('ov:stat:version')],
    ['4.5+', $str('ov:stat:moodle')],
];
echo html_writer::start_div('bcs-ov-stats');
foreach ($stats as [$value, $label]) {
    echo html_writer::div(
        html_writer::div(s($value), 'bcs-ov-stat-value') . html_writer::div(s($label), 'bcs-ov-stat-label'),
        'bcs-ov-stat'
    );
}
echo html_writer::end_div();
echo html_writer::end_div();

// Feature cards.
$features = [
    ['🧭', 'wizard'], ['📑', 'excel'], ['🧩', 'activities'], ['🔒', 'access'],
    ['🏷️', 'tags'], ['🎬', 'video'], ['📈', 'percent'], ['👁️', 'hide'], ['✏️', 'edit'],
];
echo html_writer::tag('h3', $str('ov:section:features'), ['class' => 'bcs-subhead']);
echo html_writer::start_div('bcs-ov-grid');
foreach ($features as [$icon, $key]) {
    echo html_writer::div(
        html_writer::div($icon, 'bcs-ov-icon')
        . html_writer::div($str('ov:f:' . $key), 'bcs-ov-card-title')
        . html_writer::div($str('ov:f:' . $key . '_desc'), 'bcs-ov-card-text'),
        'bcs-ov-card'
    );
}
echo html_writer::end_div();

// Quick navigation.
$links = [
    ['📊', 'ov:nav:dashboard', 'ov:nav:dashboard_desc', 'index.php'],
    ['🧭', 'ov:nav:wizard', 'ov:nav:wizard_desc', 'step_coursedetails.php'],
];
if ($canupload) {
    $links[] = ['📥', 'ov:nav:download', 'ov:nav:download_desc', 'excel_download.php'];
    $links[] = ['📤', 'ov:nav:upload', 'ov:nav:upload_desc', 'excel_upload.php'];
}
$links[] = ['✏️', 'ov:nav:edit', 'ov:nav:edit_desc', 'edit_course.php'];

echo html_writer::tag('h3', $str('ov:section:nav'), ['class' => 'bcs-subhead']);
echo html_writer::start_div('bcs-ov-nav');
foreach ($links as [$icon, $titlekey, $desckey, $page]) {
    echo html_writer::link(
        new moodle_url($base . $page),
        html_writer::span($icon, 'bcs-ov-nav-icon')
        . html_writer::span(
            html_writer::div($str($titlekey), 'bcs-ov-nav-title') . html_writer::div($str($desckey), 'bcs-ov-nav-text'),
            'bcs-ov-nav-body'
        )
        . html_writer::span('→', 'bcs-ov-nav-arrow'),
        ['class' => 'bcs-ov-navcard']
    );
}
echo html_writer::end_div();

// Developer card.
echo html_writer::start_div('bcs-ov-dev');
echo html_writer::div('MA', 'bcs-ov-avatar');
echo html_writer::tag('div', 'Michael Adeniran', ['class' => 'bcs-ov-dev-name']);
echo html_writer::tag('div', $str('ov:dev:role'), ['class' => 'bcs-ov-dev-role']);
$devlinks = [
    ['💼', 'linkedin.com/in/michaeladeniran', 'https://www.linkedin.com/in/michaeladeniran'],
    ['🐙', 'github.com/emiiyaseyi', 'https://github.com/emiiyaseyi'],
    ['✉️', 'michaeladeniransnr@gmail.com', 'mailto:michaeladeniransnr@gmail.com'],
];
foreach ($devlinks as [$icon, $label, $url]) {
    echo html_writer::div(html_writer::span($icon, 'bcs-ov-dev-icon') . html_writer::link($url, s($label)), 'bcs-ov-dev-row');
}
echo html_writer::div(
    html_writer::span('📦', 'bcs-ov-dev-icon') . s($str('pluginname') . ' ' . $release . ' · GNU GPL v3 · Moodle 4.5+'),
    'bcs-ov-dev-row text-muted'
);
echo html_writer::end_div();

echo html_writer::end_div();
echo $OUTPUT->footer();
