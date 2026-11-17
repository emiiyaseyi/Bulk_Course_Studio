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

declare(strict_types=1);

namespace mod_bulkcoursestudio\completion;

use core_completion\activity_custom_completion;

/**
 * Custom completion rule: the learner must have watched at least a set
 * percentage of the video.
 *
 * @package    mod_bulkcoursestudio
 * @copyright  2026 Bulk Course Studio
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class custom_completion extends activity_custom_completion {
    /**
     * Whether the user has met a custom rule.
     *
     * @param string $rule
     * @return int COMPLETION_COMPLETE or COMPLETION_INCOMPLETE
     */
    public function get_state(string $rule): int {
        global $DB;

        $this->validate_rule($rule);

        $required = (int) ($this->cm->customdata['customcompletionrules'][$rule] ?? 0);
        if ($required <= 0) {
            return COMPLETION_INCOMPLETE;
        }

        $progress = $DB->get_record('bulkcoursestudio_progress', [
            'bulkcoursestudioid' => $this->cm->instance,
            'userid' => $this->userid,
        ]);
        if (!$progress) {
            return COMPLETION_INCOMPLETE;
        }

        if ($rule === 'completiontimespent') {
            // The rule is in minutes; the progress is stored in seconds.
            $met = (int) $progress->timespent >= $required * 60;
        } else {
            $met = (int) $progress->watchpercent >= $required;
        }
        return $met ? COMPLETION_COMPLETE : COMPLETION_INCOMPLETE;
    }

    /**
     * The custom rules this activity defines.
     *
     * @return string[]
     */
    public static function get_defined_custom_rules(): array {
        return ['completionpercent', 'completiontimespent'];
    }

    /**
     * Text shown to learners for each active custom rule.
     *
     * @return array
     */
    public function get_custom_rule_descriptions(): array {
        $required = (int) ($this->cm->customdata['customcompletionrules']['completionpercent'] ?? 0);
        $minutes = (int) ($this->cm->customdata['customcompletionrules']['completiontimespent'] ?? 0);
        return [
            'completionpercent' => get_string('completiondetail:percent', 'mod_bulkcoursestudio', $required),
            'completiontimespent' => get_string('completiondetail:timespent', 'mod_bulkcoursestudio', $minutes),
        ];
    }

    /**
     * Order in which completion rules are listed.
     *
     * @return string[]
     */
    public function get_sort_order(): array {
        return ['completionview', 'completionpercent', 'completiontimespent'];
    }
}
