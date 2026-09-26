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
 * External function: override_completion_status.
 *
 * Doet exact hetzelfde als Moodle's eigen
 * core_completion_override_activity_completion_status, met één verschil:
 * de contextvalidatie gebeurt tegen de CURSUScontext in plaats van de
 * MODULEcontext. Moodle's kernfunctie vereist daardoor dat het aanroepende
 * (service-)account zelf ingeschreven is in de cursus - een structurele
 * vereiste (voltooiingsstatus is gegevensmodel-matig gekoppeld aan een
 * inschrijving), niet iets wat via een capability op te lossen valt.
 *
 * Deze functie omzeilt enkel die onnodig strenge contextcontrole - de
 * eigenlijke bedrijfslogica (completion_info::update_state()) is EXACT
 * dezelfde Moodle-interne aanroep die de kernfunctie ook gebruikt, dus alle
 * events, cache-invalidatie en cursusvoltooiing-herberekening lopen nog
 * steeds normaal mee. Er wordt niets rechtstreeks in de databank gewijzigd.
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

class override_completion_status extends external_api {

    /**
     * Parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'userid'   => new external_value(PARAM_INT, 'User ID van de leerling'),
            'cmid'     => new external_value(PARAM_INT, 'Course module ID van de activiteit'),
            'newstate' => new external_value(
                PARAM_INT,
                '0 = onvoltooid, 1 = voltooid, 2 = voltooid geslaagd, 3 = voltooid niet geslaagd'
            ),
        ]);
    }

    /**
     * Execute.
     *
     * Weigert expliciet (moodle_exception) als de opgegeven userid niet
     * voorkomt in de betrouwbare, zelf berekende lijst van écht
     * ingeschreven EN tot deze activiteit toegelaten leerlingen - in
     * plaats van blindelings te schrijven voor eender welke userid.
     *
     * @param int $userid
     * @param int $cmid
     * @param int $newstate
     * @return array
     */
    public static function execute(int $userid, int $cmid, int $newstate): array {
        $params = self::validate_parameters(self::execute_parameters(), [
            'userid'   => $userid,
            'cmid'     => $cmid,
            'newstate' => $newstate,
        ]);
        $userid = $params['userid'];
        $cmid = $params['cmid'];
        $newstate = $params['newstate'];

        [$course, $cm] = get_course_and_cm_from_cmid($cmid);

        // Belangrijk: we valideren tegen de CURSUScontext, niet de modulecontext -
        // zie de uitleg bovenaan dit bestand.
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

        // Weigering, geen stille no-op: enkel écht ingeschreven EN
        // toegelaten leerlingen mogen aangepast worden.
        $enrolledstudents = helper::get_enrolled_students($coursecontext);
        $allowedstudents = helper::get_allowed_students($course, $cmid, $enrolledstudents);
        if (!isset($allowedstudents[$userid])) {
            throw new \moodle_exception(
                'usernotallowed', 'local_coursecohortinfo', '',
                'userid ' . $userid . ', cmid ' . $cmid
            );
        }

        // Exact dezelfde Moodle-interne aanroep als Moodle's eigen
        // core_completion_override_activity_completion_status gebruikt.
        $completion->update_state($cm, $newstate, $userid, true);
        $completiondata = $completion->get_data($cm, false, $userid);

        return [
            'cmid'          => (int) $completiondata->coursemoduleid,
            'userid'        => (int) $completiondata->userid,
            'state'         => (int) $completiondata->completionstate,
            'timecompleted' => (int) $completiondata->timemodified,
            'overrideby'    => $completiondata->overrideby !== null ? (int) $completiondata->overrideby : 0,
            'checklist'     => array_map('intval', array_keys($allowedstudents)),
        ];
    }

    /**
     * Returns.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'cmid'          => new external_value(PARAM_INT, 'Course module ID'),
            'userid'        => new external_value(PARAM_INT, 'User ID'),
            'state'         => new external_value(PARAM_INT, 'De huidige (nieuwe) voltooiingsstatus'),
            'timecompleted' => new external_value(PARAM_INT, 'Tijdstip van voltooiing'),
            'overrideby'    => new external_value(PARAM_INT, 'User ID die de status heeft overschreven, 0 indien geen'),
            'checklist' => new external_multiple_structure(
                new external_value(PARAM_INT, 'User ID'),
                'Volledige, actuele lijst userid\'s die op dit moment toegelaten zijn tot de activiteit.'
            ),
        ]);
    }
}
