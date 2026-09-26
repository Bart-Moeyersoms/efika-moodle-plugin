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
 * External function: get_available_users_for_instance.
 *
 * Zelfde doel en resultaatvorm als get_available_users, maar neemt
 * (courseid, instance) i.p.v. cmid - opgelost via een rechtstreekse
 * databank-join ({course_modules} JOIN {modules}), NOOIT via een pad dat de
 * zichtbaarheid van het AANROEPENDE account laat meespelen (zoals
 * core_course_get_contents/mod_lti_get_ltis_by_courses dat wel doen).
 *
 * Aanleiding: bevestigd dat een sectie-brede "Toegang beperken"-voorwaarde
 * (bv. cohort-gebaseerd) een hele sectie - en dus elke activiteit erin -
 * stilzwijgend laat verdwijnen uit de respons van dat soort kernfuncties
 * voor een account dat niet aan de voorwaarde voldoet, zonder foutmelding.
 * Daardoor kon het cmid nooit gevonden worden, en viel de aanroepende
 * toepassing terug op een fail-safe ("geen beperking toepassen").
 *
 * Zodra het cmid hier gevonden is, wordt exact dezelfde, al beveiligde
 * helper::get_allowed_students() gebruikt als in get_available_users
 * (per-gebruiker uservisible-berekening via get_fast_modinfo(), NIET de
 * bulkmethode filter_user_list() - die laatste zou cohort-voorwaarden
 * opnieuw negeren, zie de uitleg in get_available_users.php).
 *
 * AANNAME: enkel activiteiten van het moduletype 'lti' (Externe tool)
 * worden opgezocht - instance-ID's zijn enkel uniek BINNEN de tabel van één
 * moduletype, dus zonder deze aanname zou eenzelfde instance-ID naar de
 * verkeerde activiteit kunnen verwijzen als er toevallig ook een
 * niet-lti-activiteit met datzelfde instance-ID bestaat.
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
use local_coursecohortinfo\local\helper;

class get_available_users_for_instance extends external_api {

    /** Enkel dit moduletype wordt opgezocht - zie AANNAME hierboven. */
    const MODULE_NAME = 'lti';

    /**
     * Parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'courseid' => new external_value(PARAM_INT, 'Course ID'),
            'instance' => new external_value(PARAM_INT, 'mod_lti instance-ID (NIET het cmid)'),
        ]);
    }

    /**
     * Execute.
     *
     * @param int $courseid
     * @param int $instance
     * @return array
     */
    public static function execute(int $courseid, int $instance): array {
        global $DB;

        $params = self::validate_parameters(self::execute_parameters(), [
            'courseid' => $courseid,
            'instance' => $instance,
        ]);
        $courseid = $params['courseid'];
        $instance = $params['instance'];

        $coursecontext = context_course::instance($courseid);
        self::validate_context($coursecontext);
        require_capability('local/coursecohortinfo:view', $coursecontext);

        // Cmid opzoeken via een rechtstreekse databank-join - geen enkel
        // zichtbaarheids-/beschikbaarheidsgevoelig pad hier, in tegenstelling
        // tot core_course_get_contents/mod_lti_get_ltis_by_courses.
        $sql = "SELECT cm.id
                  FROM {course_modules} cm
                  JOIN {modules} m ON m.id = cm.module AND m.name = :modname
                 WHERE cm.instance = :instance
                   AND cm.course = :courseid";
        $cmid = $DB->get_field_sql($sql, [
            'modname'  => self::MODULE_NAME,
            'instance' => $instance,
            'courseid' => $courseid,
        ]);

        if (!$cmid) {
            throw new \moodle_exception(
                'invalidrecordunknown', 'error', '', null,
                'Geen activiteit van type "' . self::MODULE_NAME . '" gevonden met instance '
                    . $instance . ' in cursus ' . $courseid
            );
        }
        $cmid = (int) $cmid;

        $course = get_course($courseid);

        $enrolledstudents = helper::get_enrolled_students($coursecontext);
        $allowedstudents = helper::get_allowed_students($course, $cmid, $enrolledstudents);

        $results = [];
        foreach ($allowedstudents as $user) {
            $results[] = [
                'id'       => (int) $user->id,
                'fullname' => fullname($user),
                'email'    => (string) ($user->email ?? ''),
            ];
        }

        $modinfo = get_fast_modinfo($course);
        $cminfo = $modinfo->get_cm($cmid);
        [$groups, $cohorts] = helper::extract_groups_and_cohorts($cminfo->availability);

        return [
            'cmid'          => $cmid,
            'totalenrolled' => count($enrolledstudents),
            'totalvisible'  => count($results),
            'users'         => $results,
            'groups'        => $groups,
            'cohorts'       => $cohorts,
        ];
    }

    /**
     * Returns. Zelfde vorm als get_available_users, plus het opgezochte cmid.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'cmid'          => new external_value(PARAM_INT, 'Het opgezochte Course module ID'),
            'totalenrolled' => new external_value(PARAM_INT,
                'Totaal aantal actief ingeschreven leerlingen (leerkrachten uitgesloten)'),
            'totalvisible'  => new external_value(PARAM_INT,
                'Aantal daarvan dat de activiteit mag zien'),
            'users' => new external_multiple_structure(
                new external_single_structure([
                    'id'       => new external_value(PARAM_INT, 'User ID'),
                    'fullname' => new external_value(PARAM_TEXT, 'Volledige naam'),
                    'email'    => new external_value(PARAM_TEXT, 'E-mailadres'),
                ])
            ),
            'groups' => new external_multiple_structure(
                new external_single_structure([
                    'id'   => new external_value(PARAM_INT, 'Groep-ID'),
                    'name' => new external_value(PARAM_TEXT, 'Groepsnaam'),
                ]),
                'Groepen die als voorwaarde in de beperking voorkomen (leeg als geen)',
                VALUE_DEFAULT, []
            ),
            'cohorts' => new external_multiple_structure(
                new external_single_structure([
                    'id'       => new external_value(PARAM_INT, 'Cohort-ID'),
                    'name'     => new external_value(PARAM_TEXT, 'Cohortnaam'),
                    'idnumber' => new external_value(PARAM_TEXT, 'Cohort idnumber'),
                ]),
                'Cohorten die als voorwaarde in de beperking voorkomen (leeg als geen)',
                VALUE_DEFAULT, []
            ),
        ]);
    }
}
