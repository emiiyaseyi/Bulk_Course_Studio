<?php
// This file is part of Moodle - http://moodle.org/
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

namespace tool_bulkcoursestudio\output;

/**
 * Small shared helper that gives every wizard screen the same look: a
 * progress stepper, a title block and a card wrapper. Styling lives in
 * styles.css.
 *
 * @package    tool_bulkcoursestudio
 * @copyright  2026 Bulk Course Studio
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class ui {
    /**
     * Wizard steps: key => page.
     *
     * @var string[]
     */
    const STEPS = [
        'coursedetails' => 'step_coursedetails.php',
        'format' => 'step_format.php',
        'sections' => 'step_sections.php',
        'activities' => 'step_activities.php',
        'preview' => 'step_preview.php',
    ];

    /**
     * Open a wizard screen: stepper, title, subtitle, and the content card.
     * Pair with end().
     *
     * @param string $stepkey one of the STEPS keys, or '' for no stepper
     * @param int $buildid
     * @param string $title
     * @param string $subtitle
     * @return string HTML
     */
    public static function begin(string $stepkey, int $buildid, string $title, string $subtitle = ''): string {
        $html = \html_writer::start_div('bcs');
        if ($stepkey !== '') {
            $html .= self::stepper($stepkey, $buildid);
        }
        $html .= \html_writer::start_div('bcs-hero');
        $html .= \html_writer::tag('h2', s($title), ['class' => 'bcs-title']);
        if ($subtitle !== '') {
            $html .= \html_writer::tag('p', s($subtitle), ['class' => 'bcs-subtitle']);
        }
        $html .= \html_writer::end_div();
        $html .= \html_writer::start_div('bcs-card');
        return $html;
    }

    /**
     * Close what begin() opened.
     *
     * @return string HTML
     */
    public static function end(): string {
        return \html_writer::end_div() . \html_writer::end_div();
    }

    /**
     * The numbered progress bar across the top. Finished steps are links
     * (when the build exists) so admins can jump back.
     *
     * @param string $current
     * @param int $buildid
     * @return string HTML
     */
    public static function stepper(string $current, int $buildid): string {
        $keys = array_keys(self::STEPS);
        $currentindex = array_search($current, $keys, true);
        $html = \html_writer::start_tag('ol', ['class' => 'bcs-stepper']);
        foreach ($keys as $index => $key) {
            $label = get_string('step:' . $key, 'tool_bulkcoursestudio');
            $class = 'bcs-step';
            if ($index < $currentindex) {
                $class .= ' is-done';
            } else if ($index === $currentindex) {
                $class .= ' is-current';
            }
            $number = $index < $currentindex ? '✓' : (string) ($index + 1);
            $inner = \html_writer::span($number, 'bcs-step-dot') . \html_writer::span(s($label), 'bcs-step-label');
            if ($index < $currentindex && $buildid) {
                $url = new \moodle_url('/admin/tool/bulkcoursestudio/' . self::STEPS[$key], ['buildid' => $buildid]);
                $inner = \html_writer::link($url, $inner);
            }
            $html .= \html_writer::tag('li', $inner, ['class' => $class]);
        }
        $html .= \html_writer::end_tag('ol');
        return $html;
    }
}
