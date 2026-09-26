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
 * External function: override_completion_status_bulk.
 *
 * Zelfde als override_completion_status, maar voor meerdere leerlingen
 * tegelijk in één webservice-aanroep. Faalt een individuele leerling (bv.
 * niet ingeschreven, ongeldige userid), dan gaat de verwerking voor de
 * OVERIGE leerlingen gewoon door - elk resultaat (gelukt/mislukt) wordt
 * apart teruggegeven, zodat de aanroeper per leerling kan zien wat het
 * resultaat was.
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

class override_completion_status_bulk extends external_api {

    /**
     * Parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'cmid'     => new external_value(PARAM_INT, 'Course module ID'),
            'newstate' => new external_value(
                PARAM_INT,
                '0 = onvoltooid, 1 = voltooid, 2 = voltooid geslaagd, 3 = voltooid niet geslaagd'
            ),
            'userids'  => new external_multiple_structure(
                new external_value(PARAM_INT, 'User ID'),
                'Lijst van leerling-ID\'s om aan te passen'
            ),
        ]);
    }

    /**
     * Execute.
     *
     * BELANGRIJK: een userid wordt enkel effectief aangepast als die
     * voorkomt in de betrouwbare, hier zelf berekende lijst van "écht
     * ingeschreven EN toegelaten tot deze activiteit"-leerlingen (dezelfde
     * berekening als get_available_users/get_completion_status_bulk, via
     * de gedeelde helper-klasse). Een onbestaande, niet-ingeschreven, of
     * niet-toegelaten userid wordt dus altijd expliciet geweigerd
     * (success:false) - nooit stilzwijgend als geslaagd gerapporteerd,
     * ongeacht wat de onderliggende Moodle-schrijfbewerking zelf zou
     * toelaten.
     *
     * @param int $cmid
     * @param int $newstate
     * @param array $userids
     * @return array
     */
    public static function execute(int $cmid, int $newstate, array $userids): array {
        $params = self::validate_parameters(self::execute_parameters(), [
            'cmid'     => $cmid,
            'newstate' => $newstate,
            'userids'  => $userids,
        ]);
        $cmid = $params['cmid'];
        $newstate = $params['newstate'];
        $userids = $params['userids'];

        [$course, $cm] = get_course_and_cm_from_cmid($cmid);

        $coursecontext = context_course::instance($course->id);
        self::validate_context($coursecontext);
        require_capability('local/coursecohortinfo:overridecompletion', $coursecontext);

        $completion = new completion_info($course);
        if (!$completion->is_enabled()) {
            throw new \moodle_exception('completionnotenabled', 'completion');
        }
        if (!$completion->is_enabled($cm)) {
            throw new \moodle_exception('completionnotenabledforactivity', 'local_coursecohortinfo');
        }

        // De betrouwbare checklist: enkel deze userid's mogen effectief
        // aangepast worden, ongeacht wat er in $userids gevraagd wordt.
        $enrolledstudents = helper::get_enrolled_students($coursecontext);
        $allowedstudents = helper::get_allowed_students($course, $cmid, $enrolledstudents);

        $results = [];
        foreach ($userids as $userid) {
            if (!isset($allowedstudents[$userid])) {
                $results[] = [
                    'userid'  => $userid,
                    'success' => false,
                    'state'   => 0,
                    'error'   => 'Geweigerd: deze userid is niet gevonden, niet ingeschreven in de cursus, '
                                . 'of heeft geen toegang tot deze specifieke activiteit.',
                ];
                continue;
            }

            try {
                $completion->update_state($cm, $newstate, $userid, true);
                $completiondata = $completion->get_data($cm, false, $userid);
                $results[] = [
                    'userid'  => $userid,
                    'success' => true,
                    'state'   => (int) $completiondata->completionstate,
                    'error'   => '',
                ];
            } catch (\Throwable $e) {
                // Eén mislukte leerling mag de rest van de batch niet blokkeren.
                $results[] = [
                    'userid'  => $userid,
                    'success' => false,
                    'state'   => 0,
                    'error'   => $e->getMessage(),
                ];
            }
        }

        return [
            'cmid'      => $cmid,
            'results'   => $results,
            'checklist' => array_map('intval', array_keys($allowedstudents)),
        ];
    }

    /**
     * Returns.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'cmid'    => new external_value(PARAM_INT, 'Course module ID'),
            'results' => new external_multiple_structure(
                new external_single_structure([
                    'userid'  => new external_value(PARAM_INT, 'User ID'),
                    'success' => new external_value(PARAM_BOOL, 'Of het overschrijven gelukt is voor deze leerling'),
                    'state'   => new external_value(PARAM_INT, 'De nieuwe status, indien gelukt'),
                    'error'   => new external_value(PARAM_TEXT, 'Foutmelding, indien niet gelukt (anders leeg)'),
                ])
            ),
            'checklist' => new external_multiple_structure(
                new external_value(PARAM_INT, 'User ID'),
                'Volledige, actuele lijst userid\'s die op dit moment toegelaten zijn tot de activiteit - '
                    . 'te vergelijken met de checklist van een eerdere leesaanroep om tussentijdse '
                    . 'wijzigingen aan inschrijvingen/toegang te detecteren.'
            ),
        ]);
    }
}
