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
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * External function: get_courses_using_lti_type.
 *
 * Geeft de lijst cursussen terug die minstens één Externe tool-activiteit
 * bevatten van een specifiek tooltype (mdl_lti_types.id) - zodat een
 * client-tool niet alle cursussen op de site hoeft te doorzoeken, enkel de
 * relevante.
 *
 * @package    local_coursecohortinfo
 * @copyright  2026 Efika / Dalton Gent
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

declare(strict_types=1);

namespace local_coursecohortinfo\external;

defined('MOODLE_INTERNAL') || die();

use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_multiple_structure;
use core_external\external_single_structure;
use core_external\external_value;
use context_system;

class get_courses_using_lti_type extends external_api {

    /**
     * Parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'ltitypeid' => new external_value(
                PARAM_INT,
                'ID uit mdl_lti_types (Site administration > Plugins > Activity modules > '
                    . 'External tool > Manage tools) van het tooltype waarop gefilterd wordt.'
            ),
        ]);
    }

    /**
     * Execute.
     *
     * @param int $ltitypeid
     * @return array
     */
    public static function execute(int $ltitypeid): array {
        global $DB;

        $params = self::validate_parameters(self::execute_parameters(), [
            'ltitypeid' => $ltitypeid,
        ]);
        $ltitypeid = $params['ltitypeid'];

        // Systeemcontext: dit doorzoekt cursussen sitebreed, geen enkele
        // specifieke cursus- of modulecontext van toepassing.
        self::validate_context(context_system::instance());
        require_capability('local/coursecohortinfo:view', context_system::instance());

        $sql = "SELECT DISTINCT c.id AS courseid, c.fullname AS coursename
                  FROM {course_modules} cm
                  JOIN {modules} m ON m.id = cm.module AND m.name = 'lti'
                  JOIN {lti} l ON l.id = cm.instance
                  JOIN {course} c ON c.id = cm.course
                 WHERE l.typeid = :ltitypeid
              ORDER BY c.fullname";

        $records = $DB->get_records_sql($sql, ['ltitypeid' => $ltitypeid]);

        $results = [];
        foreach ($records as $r) {
            $results[] = [
                'courseid'   => (int) $r->courseid,
                'coursename' => (string) $r->coursename,
            ];
        }

        return $results;
    }

    /**
     * Returns.
     *
     * @return external_multiple_structure
     */
    public static function execute_returns(): external_multiple_structure {
        return new external_multiple_structure(
            new external_single_structure([
                'courseid'   => new external_value(PARAM_INT, 'Course ID'),
                'coursename' => new external_value(PARAM_TEXT, 'Volledige cursusnaam'),
            ])
        );
    }
}
