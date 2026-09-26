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
 * External function: get_completion_status_bulk.
 *
 * Geeft, voor een cmid, de voltooiingsstatus van ALLE ingeschreven leerlingen
 * terug in één webservice-aanroep - in plaats van dat de client N keer
 * core_completion_get_activities_completion_status moet aanroepen (één per
 * leerling). De N-per-leerling-lus gebeurt hier server-side, binnen Moodle
 * zelf, niet als N aparte HTTP-aanroepen vanuit de client.
 *
 * @package    local_coursecohortinfo
 * @copyright  2026 Efika / Dalton Gent
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

declare(strict_types=1);

namespace local_coursecohortinfo\external;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->libdir . '/completionlib.php');

use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_multiple_structure;
use core_external\external_single_structure;
use core_external\external_value;
use context_course;
use completion_info;
use local_coursecohortinfo\local\helper;

class get_completion_status_bulk extends external_api {

    /**
     * Parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'cmid' => new external_value(PARAM_INT, 'Course module ID'),
        ]);
    }

    /**
     * Execute.
     *
     * Geeft enkel leerlingen terug die de activiteit effectief mogen zien
     * (toegelaten volgens Toegang beperken) - niet iedereen die toevallig
     * in de cursus ingeschreven is. De 'checklist' bevat expliciet de
     * volledige lijst toegelaten userid's op het moment van deze aanroep,
     * zodat de aanroepende toepassing dit kan bewaren en later kan
     * vergelijken met de checklist van een schrijfaanroep - zo valt op te
     * merken of er tussentijds iets aan de inschrijvingen/toegang
     * gewijzigd is.
     *
     * @param int $cmid
     * @return array
     */
    public static function execute(int $cmid): array {
        $params = self::validate_parameters(self::execute_parameters(), ['cmid' => $cmid]);
        $cmid = $params['cmid'];

        [$course, $cm] = get_course_and_cm_from_cmid($cmid);

        $coursecontext = context_course::instance($course->id);
        self::validate_context($coursecontext);
        require_capability('local/coursecohortinfo:view', $coursecontext);

        $enrolledstudents = helper::get_enrolled_students($coursecontext);
        $allowedstudents = helper::get_allowed_students($course, $cmid, $enrolledstudents);

        $completion = new completion_info($course);
        $completionenabled = $completion->is_enabled($cm);

        $results = [];
        foreach ($allowedstudents as $userid => $user) {
            $state = null;
            $timecompleted = 0;
            $overrideby = 0;
            if ($completionenabled) {
                $completiondata = $completion->get_data($cm, false, $userid);
                $state = (int) $completiondata->completionstate;
                $timecompleted = (int) $completiondata->timemodified;
                $overrideby = $completiondata->overrideby !== null ? (int) $completiondata->overrideby : 0;
            }

            $results[] = [
                'userid'        => (int) $userid,
                'fullname'      => fullname($user),
                'email'         => (string) ($user->email ?? ''),
                'state'         => $state,
                'timecompleted' => $timecompleted,
                'overrideby'    => $overrideby,
            ];
        }

        return [
            'cmid'              => $cmid,
            'completionenabled' => $completionenabled,
            'users'             => $results,
            'checklist'         => array_map('intval', array_keys($allowedstudents)),
        ];
    }

    /**
     * Returns.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'cmid'              => new external_value(PARAM_INT, 'Course module ID'),
            'completionenabled' => new external_value(PARAM_BOOL, 'Of voltooiing volgen aanstaat voor deze activiteit'),
            'users' => new external_multiple_structure(
                new external_single_structure([
                    'userid'        => new external_value(PARAM_INT, 'User ID'),
                    'fullname'      => new external_value(PARAM_TEXT, 'Volledige naam'),
                    'email'         => new external_value(PARAM_TEXT, 'E-mailadres'),
                    'state'         => new external_value(PARAM_INT,
                        '0=onvoltooid, 1=voltooid, 2=voltooid geslaagd, 3=voltooid niet geslaagd', VALUE_OPTIONAL),
                    'timecompleted' => new external_value(PARAM_INT, 'Tijdstip van voltooiing, 0 indien niet van toepassing'),
                    'overrideby'    => new external_value(PARAM_INT, 'User ID die de status overschreef, 0 indien geen'),
                ])
            ),
            'checklist' => new external_multiple_structure(
                new external_value(PARAM_INT, 'User ID'),
                'Volledige, actuele lijst userid\'s die op dit moment toegelaten zijn tot de activiteit - '
                    . 'te bewaren en later te vergelijken om tussentijdse wijzigingen te detecteren.'
            ),
        ]);
    }
}
