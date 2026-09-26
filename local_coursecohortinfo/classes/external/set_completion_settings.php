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
 * External function: set_completion_settings.
 *
 * Kan zowel de "Voltooiing volgen"-modus (completionmode) als de "Verwacht
 * voltooid op"-datum (completionexpected) instellen, samen of apart - nodig
 * omdat een opdracht zonder enige voltooiingsvoorwaarde (completionmode = 0)
 * een datum instellen zonder het eerst mogelijk te maken zinledig zou zijn
 * in Moodle's eigen UI (die het datumveld dan niet eens toont).
 *
 * Bescherming: als de UITEINDELIJKE completionmode 0 (uit) is - ongeacht of
 * dat de bestaande waarde was of net expliciet zo aangevraagd werd - wordt
 * completionexpected altijd geforceerd op 0 gezet, ook als er een andere
 * waarde werd meegegeven. Er kan dus nooit een datum blijven staan op een
 * activiteit waar voltooiing volgen uitstaat. De respons meldt expliciet
 * of deze correctie is toegepast (forcedexpecteddatetocleared).
 *
 * Roept ook expliciet \core_completion\api::update_completion_date_event()
 * aan - dit is de stap die de kalender-actiegebeurtenis (Tijdlijn-weergave)
 * effectief aanmaakt/bijwerkt; een rechtstreekse databankwijziging alleen
 * doet dit niet.
 *
 * SCHRIJFACTIE - enkel op de write-service plaatsen.
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

class set_completion_settings extends external_api {

    /** Sentinel-waarde: "laat dit veld ongewijzigd". */
    const UNCHANGED = -1;

    /**
     * Parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'cmid'               => new external_value(PARAM_INT, 'Course module ID'),
            'completionmode'     => new external_value(
                PARAM_INT,
                '0=uit, 1=manueel, 2=automatisch. Geef -1 mee om ongewijzigd te laten.',
                VALUE_DEFAULT,
                self::UNCHANGED
            ),
            'completionexpected' => new external_value(
                PARAM_INT,
                'Unix-timestamp van "Verwacht voltooid op". Geef 0 mee om te wissen, '
                    . '-1 om ongewijzigd te laten.',
                VALUE_DEFAULT,
                self::UNCHANGED
            ),
        ]);
    }

    /**
     * Execute.
     *
     * @param int $cmid
     * @param int $completionmode
     * @param int $completionexpected
     * @return array
     */
    public static function execute(int $cmid, int $completionmode, int $completionexpected): array {
        global $DB;

        $params = self::validate_parameters(self::execute_parameters(), [
            'cmid'               => $cmid,
            'completionmode'     => $completionmode,
            'completionexpected' => $completionexpected,
        ]);
        $cmid = $params['cmid'];
        $completionmode = $params['completionmode'];
        $completionexpected = $params['completionexpected'];

        if ($completionmode !== self::UNCHANGED && !in_array($completionmode, [0, 1, 2], true)) {
            throw new \invalid_parameter_exception('completionmode moet -1, 0, 1 of 2 zijn');
        }
        if ($completionexpected !== self::UNCHANGED && $completionexpected < 0) {
            throw new \invalid_parameter_exception('completionexpected mag niet negatief zijn (behalve -1 = ongewijzigd)');
        }

        [$course, $cm] = get_course_and_cm_from_cmid($cmid);

        $coursecontext = context_course::instance($course->id);
        self::validate_context($coursecontext);
        require_capability('moodle/course:manageactivities', $coursecontext);

        $completion = new completion_info($course);
        if (!$completion->is_enabled()) {
            throw new \moodle_exception(
                'completionnotenabled', 'completion', '', null,
                'Voltooiing volgen staat uit op cursusniveau - schakel dit eerst in via de '
                    . 'cursusinstellingen vóór je dit per activiteit kan instellen.'
            );
        }

        $cmrecord = $DB->get_record('course_modules', ['id' => $cmid], '*', MUST_EXIST);

        // Bepaal de uiteindelijke waarden: expliciet meegegeven, of de
        // bestaande waarde behouden.
        $finalcompletionmode = ($completionmode === self::UNCHANGED)
            ? (int) $cmrecord->completion
            : $completionmode;
        $finalcompletionexpected = ($completionexpected === self::UNCHANGED)
            ? (int) $cmrecord->completionexpected
            : $completionexpected;

        // Bescherming tegen de inconsistente combinatie: geen actieve
        // voltooiing, maar toch een datum ingesteld.
        $forcedexpecteddatetocleared = false;
        if ($finalcompletionmode === 0 && $finalcompletionexpected > 0) {
            $finalcompletionexpected = 0;
            $forcedexpecteddatetocleared = true;
        }

        $DB->set_field('course_modules', 'completion', $finalcompletionmode, ['id' => $cmid]);
        $DB->set_field('course_modules', 'completionexpected', $finalcompletionexpected, ['id' => $cmid]);

        // Dit is de stap die eerder ontbrak: het rechtstreeks wijzigen van
        // course_modules.completionexpected past enkel het ruwe veld aan,
        // maar maakt/werkt NIET de kalender-actiegebeurtenis bij die het
        // Tijdlijn-blok/Dashboard effectief leest - dat is een apart record.
        // Dit is exact dezelfde aanroep die Moodle's eigen bewerkingsformulier
        // na het opslaan doet (zie bv. mod/lti/lib.php).
        \core_completion\api::update_completion_date_event(
            (int) $cm->id,
            $cm->modname,
            $cm->instance,
            $finalcompletionexpected > 0 ? $finalcompletionexpected : null
        );

        // Cache verplicht herbouwen na een rechtstreekse wijziging aan
        // course_modules. $course->id komt uit Moodle's databanklaag als
        // string terug; met strict_types=1 moet dit expliciet naar int
        // gecast worden vóór een aanroep van een strikt int-getypeerde
        // kernfunctie.
        rebuild_course_cache((int) $course->id, true);

        // Zelfde event dat Moodle's eigen instellingenformulier afvuurt, voor
        // consistente logging/observeerbaarheid.
        $event = \core\event\course_module_updated::create_from_cm($cm, $coursecontext);
        $event->trigger();

        return [
            'cmid'                        => $cmid,
            'completionmode'              => $finalcompletionmode,
            'completionexpected'          => $finalcompletionexpected,
            'forcedexpecteddatetocleared' => $forcedexpecteddatetocleared,
        ];
    }

    /**
     * Returns.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'cmid'                        => new external_value(PARAM_INT, 'Course module ID'),
            'completionmode'              => new external_value(PARAM_INT, 'De nu geldende voltooiingsmodus (0/1/2)'),
            'completionexpected'          => new external_value(PARAM_INT, 'De nu geldende "Verwacht voltooid op"-datum, 0 indien geen'),
            'forcedexpecteddatetocleared' => new external_value(
                PARAM_BOOL,
                'True als de datum automatisch gewist is omdat voltooiingsmodus 0 (uit) uitkwam - '
                    . 'ongeacht wat er voor completionexpected werd meegegeven'
            ),
        ]);
    }
}
