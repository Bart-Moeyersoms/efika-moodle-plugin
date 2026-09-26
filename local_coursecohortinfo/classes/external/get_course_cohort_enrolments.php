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
 * External function: get_course_cohort_enrolments.
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
use context_course;

/**
 * Geeft, voor een gegeven cursus, alle "Sitegroepsynchronisatie" (cohort)
 * inschrijvingsmethodes terug, met de naam en het idnumber van het gekoppelde
 * cohort en, indien aanwezig, het ID van de automatisch aangemaakte cursusgroep.
 *
 * Enkel lezend: er wordt niets aangemaakt, gewijzigd of verwijderd.
 */
class get_course_cohort_enrolments extends external_api {

    /**
     * Parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'courseid' => new external_value(PARAM_INT, 'Course ID'),
        ]);
    }

    /**
     * Execute.
     *
     * @param int $courseid
     * @return array
     */
    public static function execute(int $courseid): array {
        global $DB;

        $params = self::validate_parameters(self::execute_parameters(), [
            'courseid' => $courseid,
        ]);
        $courseid = $params['courseid'];

        // Context- en capability-check: enkel wie in DEZE cursus expliciet de
        // capability heeft, kan dit opvragen. Geen bypass, geen brede rechten nodig.
        $context = context_course::instance($courseid);
        self::validate_context($context);
        require_capability('local/coursecohortinfo:view', $context);

        // Geparametriseerde query - geen ruwe invoer wordt ooit rechtstreeks
        // in de SQL-string geplakt.
        $sql = "SELECT e.id AS enrolid,
                       e.courseid,
                       e.customint1 AS cohortid,
                       co.name AS cohortname,
                       co.idnumber AS cohortidnumber,
                       e.customint2 AS groupid,
                       e.roleid,
                       e.status
                  FROM {enrol} e
             LEFT JOIN {cohort} co ON co.id = e.customint1
                 WHERE e.enrol = :enrolplugin
                   AND e.courseid = :courseid";

        $records = $DB->get_records_sql($sql, [
            'enrolplugin' => 'cohort',
            'courseid'    => $courseid,
        ]);

        $results = [];
        foreach ($records as $r) {
            $results[] = [
                'enrolid'        => (int) $r->enrolid,
                'courseid'       => (int) $r->courseid,
                'cohortid'       => $r->cohortid !== null ? (int) $r->cohortid : 0,
                'cohortname'     => (string) ($r->cohortname ?? ''),
                'cohortidnumber' => (string) ($r->cohortidnumber ?? ''),
                'groupid'        => $r->groupid !== null ? (int) $r->groupid : 0,
                'roleid'         => (int) $r->roleid,
                'status'         => (int) $r->status,
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
                'enrolid'        => new external_value(PARAM_INT, 'Enrolment instance ID (mdl_enrol.id)'),
                'courseid'       => new external_value(PARAM_INT, 'Course ID'),
                'cohortid'       => new external_value(PARAM_INT,
                    'Cohort ID (0 as het gekoppelde cohort niet meer bestaat)'),
                'cohortname'     => new external_value(PARAM_TEXT, 'Naam van het cohort'),
                'cohortidnumber' => new external_value(PARAM_TEXT, 'Idnumber van het cohort'),
                'groupid'        => new external_value(PARAM_INT,
                    'ID van de gekoppelde cursusgroep, 0 als geen groep aangemaakt is'),
                'roleid'         => new external_value(PARAM_INT, 'Rol toegekend aan cohortleden'),
                'status'         => new external_value(PARAM_INT, '0 = actief, 1 = uitgeschakeld'),
            ])
        );
    }
}
