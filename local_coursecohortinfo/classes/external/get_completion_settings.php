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
 * External function: get_completion_settings.
 *
 * Geeft de "Voltooiingsvoorwaarden"-instellingen van een activiteit terug -
 * onder meer of "Verwacht voltooid op" (completionexpected) al dan niet is
 * ingesteld, zodat een LTI-toepassing kan detecteren of dit bij het
 * aanmaken van een opdracht vergeten is.
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
use core_external\external_single_structure;
use core_external\external_value;
use context_course;
use completion_info;

class get_completion_settings extends external_api {

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
     * @param int $cmid
     * @return array
     */
    public static function execute(int $cmid): array {
        global $DB;

        $params = self::validate_parameters(self::execute_parameters(), ['cmid' => $cmid]);
        $cmid = $params['cmid'];

        [$course, $cm] = get_course_and_cm_from_cmid($cmid);

        $coursecontext = context_course::instance($course->id);
        self::validate_context($coursecontext);
        require_capability('local/coursecohortinfo:view', $coursecontext);

        $completion = new completion_info($course);
        $completionenabledforcourse = $completion->is_enabled();

        $cmrecord = $DB->get_record('course_modules', ['id' => $cmid], '*', MUST_EXIST);

        // completion: 0 = uit, 1 = manueel, 2 = automatisch (COMPLETION_TRACKING_*).
        $completionmode = (int) $cmrecord->completion;
        $hasexpecteddate = (int) $cmrecord->completionexpected > 0;

        // Elke activiteit zonder "Verwacht voltooid op"-datum wordt als
        // "vermoedelijk vergeten" beschouwd - ook als completionmode nog op
        // 0 (uit) staat. Dat laatste geval is net het duidelijkste signaal
        // dat er bij het aanmaken niets is ingesteld: gebruik dan
        // set_completion_settings om completionmode én completionexpected
        // in één aanroep alsnog correct te zetten.
        $likelyforgottendeadline = !$hasexpecteddate;

        return [
            'cmid'                     => $cmid,
            'completionenabledcourse'  => $completionenabledforcourse,
            'completionmode'           => $completionmode,
            'completionview'           => (bool) $cmrecord->completionview,
            'completionexpected'       => (int) $cmrecord->completionexpected,
            'hasexpecteddate'          => $hasexpecteddate,
            'completionpassgrade'      => (bool) $cmrecord->completionpassgrade,
            'likelyforgottendeadline'  => $likelyforgottendeadline,
        ];
    }

    /**
     * Returns.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'cmid'                    => new external_value(PARAM_INT, 'Course module ID'),
            'completionenabledcourse' => new external_value(PARAM_BOOL, 'Of "Voltooiing volgen" aanstaat op cursusniveau'),
            'completionmode'          => new external_value(PARAM_INT, '0=uit, 1=manueel, 2=automatisch'),
            'completionview'          => new external_value(PARAM_BOOL, 'Of "bekeken" als voltooiingsvoorwaarde geldt'),
            'completionexpected'      => new external_value(PARAM_INT, 'Unix-timestamp van "Verwacht voltooid op", 0 indien niet ingesteld'),
            'hasexpecteddate'         => new external_value(PARAM_BOOL, 'Of er een "Verwacht voltooid op"-datum is ingesteld'),
            'completionpassgrade'     => new external_value(PARAM_BOOL, 'Of "geslaagd cijfer" als voltooiingsvoorwaarde geldt'),
            'likelyforgottendeadline' => new external_value(PARAM_BOOL,
                'Er is geen "Verwacht voltooid op"-datum ingesteld - ongeacht completionmode - '
                    . 'vermoedelijk (nog) niet ingesteld bij het aanmaken'),
        ]);
    }
}
