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
 * Gedeelde hulpfuncties, herbruikt door get_available_users,
 * get_completion_status_bulk en override_completion_status_bulk. Bepaalt
 * telkens dezelfde, betrouwbare "wie is écht ingeschreven en toegelaten
 * tot deze activiteit"-lijst - rechtstreeks bij Moodle berekend, nooit
 * enkel gebaseerd op wat een aanroeper zelf meegeeft.
 *
 * @package    local_coursecohortinfo
 * @copyright  2026 Efika / Dalton Gent
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

declare(strict_types=1);

namespace local_coursecohortinfo\local;

defined('MOODLE_INTERNAL') || die();

class helper {

    /**
     * Geeft alle actief ingeschreven gebruikers van een cursus terug,
     * leerkrachten/niet-bewerkende leerkrachten uitgesloten (archetype-
     * gebaseerd, hernoemingsbestendig).
     *
     * @param \context_course $coursecontext
     * @return array Sleutel = userid, waarde = user-object.
     */
    public static function get_enrolled_students(\context_course $coursecontext): array {
        global $DB;

        $enrolledusers = get_enrolled_users($coursecontext, '', 0, 'u.*', null, 0, 0, true);

        $teacherroleids = $DB->get_fieldset_select(
            'role', 'id', "archetype IN ('editingteacher', 'teacher')"
        );
        $teacheruserids = [];
        if (!empty($teacherroleids)) {
            [$insql, $inparams] = $DB->get_in_or_equal($teacherroleids, SQL_PARAMS_NAMED, 'role');
            $inparams['contextid'] = $coursecontext->id;
            $teacheruserids = $DB->get_fieldset_select(
                'role_assignments', 'DISTINCT userid',
                "contextid = :contextid AND roleid $insql", $inparams
            );
        }

        $result = [];
        foreach ($enrolledusers as $user) {
            if (!in_array($user->id, $teacheruserids)) {
                $result[(int) $user->id] = $user;
            }
        }
        return $result;
    }

    /**
     * Filtert een lijst ingeschreven gebruikers tot enkel wie de opgegeven
     * activiteit effectief mag zien (per-gebruiker uservisible-berekening,
     * zelfde methode als get_available_users - zie de uitleg daar over
     * waarom dit NIET via de snellere bulk-filter_user_list() gebeurt).
     *
     * @param object $course
     * @param int $cmid
     * @param array $enrolledusers Sleutel = userid, waarde = user-object.
     * @return array Sleutel = userid, waarde = user-object. Enkel toegelaten leerlingen.
     */
    public static function get_allowed_students($course, int $cmid, array $enrolledusers): array {
        $allowed = [];
        foreach ($enrolledusers as $userid => $user) {
            $usermodinfo = get_fast_modinfo($course, $userid);
            $usercminfo = $usermodinfo->get_cm($cmid);
            if ($usercminfo->uservisible) {
                $allowed[$userid] = $user;
            }
        }
        return $allowed;
    }

    /**
     * Doorloopt de ruwe availability-JSON en haalt alle group/cohort-verwijzingen
     * eruit, inclusief hun naam (opgezocht in de databank). Geeft lege arrays
     * terug als er geen beperking is, of als er geen groep/cohort-voorwaarden
     * tussen zitten.
     *
     * @param string|null $availabilityjson
     * @return array [groups, cohorts] - elk een lijst van ['id' => .., 'name' => ..]
     */
    public static function extract_groups_and_cohorts(?string $availabilityjson): array {
        global $DB;

        $groupids = [];
        $cohortids = [];

        if (!empty($availabilityjson)) {
            $tree = json_decode($availabilityjson, true);
            if (is_array($tree)) {
                self::walk_availability_tree($tree, $groupids, $cohortids);
            }
        }

        $groups = [];
        if (!empty($groupids)) {
            $records = $DB->get_records_list('groups', 'id', array_unique($groupids), '', 'id, name');
            foreach ($records as $r) {
                $groups[] = ['id' => (int) $r->id, 'name' => (string) $r->name];
            }
        }

        $cohorts = [];
        if (!empty($cohortids)) {
            $records = $DB->get_records_list('cohort', 'id', array_unique($cohortids), '', 'id, name, idnumber');
            foreach ($records as $r) {
                $cohorts[] = [
                    'id'       => (int) $r->id,
                    'name'     => (string) $r->name,
                    'idnumber' => (string) ($r->idnumber ?? ''),
                ];
            }
        }

        return [$groups, $cohorts];
    }

    /**
     * Recursieve helper: verzamelt group- en cohort-ID's uit een (mogelijk
     * geneste) availability-boomstructuur.
     *
     * @param array $node
     * @param array $groupids Doorgegeven bij referentie, wordt aangevuld.
     * @param array $cohortids Doorgegeven bij referentie, wordt aangevuld.
     */
    private static function walk_availability_tree(array $node, array &$groupids, array &$cohortids): void {
        if (empty($node['c']) || !is_array($node['c'])) {
            return;
        }
        foreach ($node['c'] as $child) {
            if (!is_array($child)) {
                continue;
            }
            if (isset($child['op'])) {
                // Geneste sub-boom (Restriction set).
                self::walk_availability_tree($child, $groupids, $cohortids);
                continue;
            }
            if (($child['type'] ?? null) === 'group' && isset($child['id'])) {
                $groupids[] = (int) $child['id'];
            } else if (($child['type'] ?? null) === 'cohort' && isset($child['id'])) {
                $cohortids[] = (int) $child['id'];
            }
            // grouping wordt bewust niet uitgesplitst tot individuele groepen -
            // dat kan je apart opvragen via core_group_get_course_groupings als nodig.
        }
    }
}
