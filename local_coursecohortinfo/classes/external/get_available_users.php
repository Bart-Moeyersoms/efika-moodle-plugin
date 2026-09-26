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
 * External function: get_available_users.
 *
 * Berekent, per ingeschreven gebruiker apart, of een activiteit voor hen
 * zichtbaar is - via get_fast_modinfo() met een specifiek userid. Dit is
 * bewust GEEN gebruik van de snellere \core_availability\info_module::
 * filter_user_list()-bulkmethode, omdat die enkel voorwaarden toepast
 * waarvoor de betrokken availability-plugin expliciet
 * "is_applied_to_user_lists() => true" implementeert (bv. Groep, Groepering -
 * niet noodzakelijk Cohort). De per-gebruiker aanpak hier is trager bij grote
 * cursussen, maar werkt gegarandeerd correct voor élke voorwaarde en élke
 * combinatie van EN/OF/NIET, ongeacht welke availability-plugins de
 * bulk-optimalisatie wel of niet ondersteunen.
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

class get_available_users extends external_api {

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

        $params = self::validate_parameters(self::execute_parameters(), [
            'cmid' => $cmid,
        ]);
        $cmid = $params['cmid'];

        [$course, $cm] = get_course_and_cm_from_cmid($cmid);

        // Belangrijk: we valideren tegen de CURSUScontext, niet de modulecontext.
        // Moodle's eigen validate_context() voert voor een modulecontext ook een
        // require_login-achtige controle uit die zou vereisen dat het AANROEPENDE
        // account zelf aan de Toegang beperken-voorwaarden van deze ene activiteit
        // voldoet - net wat we willen kunnen omzeilen voor een rapporterend
        // service-account.
        $coursecontext = context_course::instance($cm->course);
        self::validate_context($coursecontext);
        require_capability('local/coursecohortinfo:view', $coursecontext);
        $enrolledusers = get_enrolled_users($coursecontext, '', 0, 'u.*', null, 0, 0, true);

        // Leerkrachten (en niet-bewerkende leerkrachten) eruit filteren: die
        // hebben doorgaans sowieso brede rechten (bv. viewhiddenactivities),
        // waardoor ze voor zichzelf altijd "zichtbaar" scoren ongeacht de
        // beperking - ze horen dus niet tussen de leerlingen die effectief aan
        // de voorwaarde moeten voldoen. Gebaseerd op archetype, niet rolnaam,
        // zodat een hernoemde rol dit niet laat falen.
        $teacherroleids = $DB->get_fieldset_select(
            'role', 'id', "archetype IN ('editingteacher', 'teacher')"
        );
        $teacheruserids = [];
        if (!empty($teacherroleids)) {
            [$insql, $inparams] = $DB->get_in_or_equal($teacherroleids, SQL_PARAMS_NAMED, 'role');
            $inparams['contextid'] = $coursecontext->id;
            $teacheruserids = $DB->get_fieldset_select(
                'role_assignments',
                'DISTINCT userid',
                "contextid = :contextid AND roleid $insql",
                $inparams
            );
        }
        $enrolledusers = array_filter($enrolledusers, function ($user) use ($teacheruserids) {
            return !in_array($user->id, $teacheruserids);
        });

        // BELANGRIJK: we gebruiken hier bewust NIET \core_availability\info_module::
        // filter_user_list() op de volledige lijst tegelijk. Die bulk-methode is
        // sneller, maar past enkel voorwaarden toe waarvoor de betrokken
        // availability-plugin expliciet "is_applied_to_user_lists() => true"
        // implementeert. De cohort-plugin (availability_cohort) doet dat niet,
        // waardoor cohort-voorwaarden bij de bulk-methode stilzwijgend genegeerd
        // worden - met als gevolg dat iedereen als "zichtbaar" werd getoond.
        //
        // In plaats daarvan berekenen we per ingeschreven gebruiker apart of de
        // activiteit voor HEN zichtbaar is (get_fast_modinfo() met een specifiek
        // userid). Dat gebruikt exact dezelfde berekening die een leerling zelf
        // ondervindt bij het openen van de activiteit, en houdt dus correct
        // rekening met ELKE voorwaarde en ELKE EN/OF/NIET-combinatie, ongeacht
        // welke availability-plugins wel of niet de bulk-optimalisatie
        // ondersteunen. Trager dan filter_user_list() bij grote cursussen, maar
        // voor een normale klasomvang ruimschoots snel genoeg, en altijd correct.
        $results = [];
        foreach ($enrolledusers as $user) {
            $usermodinfo = get_fast_modinfo($course, $user->id);
            $usercminfo = $usermodinfo->get_cm($cmid);
            if ($usercminfo->uservisible) {
                $results[] = [
                    'id'        => (int) $user->id,
                    'fullname'  => fullname($user),
                    'email'     => (string) ($user->email ?? ''),
                ];
            }
        }

        // Ruwe availability-JSON van de activiteit zelf (voor de weergave van
        // groepen/cohorten in de respons) - dit is contextonafhankelijk, dus
        // één keer ophalen volstaat.
        $modinfo = get_fast_modinfo($course);
        $cminfo = $modinfo->get_cm($cmid);

        // Welke groepen/cohorten liggen aan de basis van deze beperking?
        // Rechtstreeks uit de ruwe availability-JSON gehaald en met naam
        // opgezocht in de databank - geen JS-parsing meer nodig voor dit deel.
        [$groups, $cohorts] = helper::extract_groups_and_cohorts($cminfo->availability);

        return [
            'cmid'          => $cmid,
            'totalenrolled' => count($enrolledusers),
            'totalvisible'  => count($results),
            'users'         => $results,
            'groups'        => $groups,
            'cohorts'       => $cohorts,
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
                'Cohorten die als voorwaarde in de beperking voorkomen (leeg als geen, of als availability_cohort niet geïnstalleerd is)',
                VALUE_DEFAULT, []
            ),
        ]);
    }
}
