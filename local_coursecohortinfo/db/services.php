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
 * Webservice definitions for local_coursecohortinfo.
 *
 * @package    local_coursecohortinfo
 * @copyright  2026 Efika / Dalton Gent
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$functions = [
    'local_coursecohortinfo_get_course_cohort_enrolments' => [
        'classname'     => 'local_coursecohortinfo\external\get_course_cohort_enrolments',
        'methodname'    => 'execute',
        'description'   => 'Geeft de cohort-gebaseerde inschrijvingsmethodes van een cursus terug, '
                            . 'met cohortnaam, idnumber en gekoppelde cursusgroep.',
        'type'          => 'read',
        'capabilities'  => 'local/coursecohortinfo:view',
        'ajax'          => false,
    ],
    'local_coursecohortinfo_get_available_users' => [
        'classname'     => 'local_coursecohortinfo\external\get_available_users',
        'methodname'    => 'execute',
        'description'   => 'Geeft, via Moodle\'s eigen availability-API (geen eigen JSON-parsing), '
                            . 'de effectieve lijst van ingeschreven gebruikers terug die een activiteit '
                            . 'mogen zien op basis van permanente Toegang beperken-voorwaarden '
                            . '(groep, groepering, cohort, profielveld, voltooiing). Datum- en '
                            . 'cijfervoorwaarden worden hier niet in meegenomen.',
        'type'          => 'read',
        'capabilities'  => 'local/coursecohortinfo:view',
        'ajax'          => false,
    ],
    'local_coursecohortinfo_get_available_users_for_instance' => [
        'classname'     => 'local_coursecohortinfo\external\get_available_users_for_instance',
        'methodname'    => 'execute',
        'description'   => 'Zelfde als get_available_users, maar neemt (courseid, instance) i.p.v. '
                            . 'cmid - lost het cmid op via een rechtstreekse databank-join, zodat een '
                            . 'sectie-brede beperking het cmid niet langer onvindbaar kan maken zoals '
                            . 'bij core_course_get_contents. Enkel voor moduletype "lti".',
        'type'          => 'read',
        'capabilities'  => 'local/coursecohortinfo:view',
        'ajax'          => false,
    ],
    'local_coursecohortinfo_get_courses_using_lti_type' => [
        'classname'     => 'local_coursecohortinfo\external\get_courses_using_lti_type',
        'methodname'    => 'execute',
        'description'   => 'Geeft de lijst cursussen terug die minstens één Externe tool-activiteit '
                            . 'bevatten van een specifiek, opgegeven tooltype (lti_types.id).',
        'type'          => 'read',
        'capabilities'  => 'local/coursecohortinfo:view',
        'ajax'          => false,
    ],
    'local_coursecohortinfo_override_completion_status' => [
        'classname'     => 'local_coursecohortinfo\external\override_completion_status',
        'methodname'    => 'execute',
        'description'   => 'Overschrijft de voltooiingsstatus van een leerling voor een activiteit - '
                            . 'zelfde bedrijfslogica als core_completion_override_activity_completion_status, '
                            . 'maar gevalideerd tegen de cursuscontext, zodat het aanroepende '
                            . 'service-account niet zelf ingeschreven moet zijn in de cursus. '
                            . 'SCHRIJFACTIE - enkel op de write-service plaatsen, nooit op de leesservice.',
        'type'          => 'write',
        'capabilities'  => 'local/coursecohortinfo:overridecompletion',
        'ajax'          => false,
    ],
    'local_coursecohortinfo_get_completion_status_bulk' => [
        'classname'     => 'local_coursecohortinfo\external\get_completion_status_bulk',
        'methodname'    => 'execute',
        'description'   => 'Geeft de voltooiingsstatus van ALLE ingeschreven leerlingen (leerkrachten '
                            . 'uitgesloten) voor een activiteit terug in één aanroep, in plaats van '
                            . 'N aparte aanroepen met core_completion_get_activities_completion_status.',
        'type'          => 'read',
        'capabilities'  => 'local/coursecohortinfo:view',
        'ajax'          => false,
    ],
    'local_coursecohortinfo_override_completion_status_bulk' => [
        'classname'     => 'local_coursecohortinfo\external\override_completion_status_bulk',
        'methodname'    => 'execute',
        'description'   => 'Overschrijft de voltooiingsstatus van meerdere leerlingen in één aanroep. '
                            . 'SCHRIJFACTIE - enkel op de write-service plaatsen, nooit op de leesservice.',
        'type'          => 'write',
        'capabilities'  => 'local/coursecohortinfo:overridecompletion',
        'ajax'          => false,
    ],
    'local_coursecohortinfo_get_completion_settings' => [
        'classname'     => 'local_coursecohortinfo\external\get_completion_settings',
        'methodname'    => 'execute',
        'description'   => 'Geeft de "Voltooiingsvoorwaarden"-instellingen van een activiteit terug, '
                            . 'inclusief of een "Verwacht voltooid op"-datum ontbreekt.',
        'type'          => 'read',
        'capabilities'  => 'local/coursecohortinfo:view',
        'ajax'          => false,
    ],
    'local_coursecohortinfo_set_completion_settings' => [
        'classname'     => 'local_coursecohortinfo\external\set_completion_settings',
        'methodname'    => 'execute',
        'description'   => 'Stelt de voltooiingsmodus (completionmode) en/of de "Verwacht voltooid op"-datum '
                            . 'van een activiteit in, samen of apart. Voorkomt de inconsistente combinatie '
                            . '"datum ingesteld terwijl voltooiing volgen uitstaat" door de datum automatisch '
                            . 'te wissen als de uiteindelijke voltooiingsmodus 0 (uit) is. '
                            . 'SCHRIJFACTIE - enkel op de write-service plaatsen, nooit op de leesservice.',
        'type'          => 'write',
        'capabilities'  => 'moodle/course:manageactivities',
        'ajax'          => false,
    ],
    'local_coursecohortinfo_send_deadline_notification' => [
        'classname'     => 'local_coursecohortinfo\external\send_deadline_notification',
        'methodname'    => 'execute',
        'description'   => 'Stuurt een persoonlijke systeemnotificatie (geen instant message) naar één '
                            . 'specifieke, toegelaten leerling - bv. voor een individuele verlenging. '
                            . 'Omzeilt bewust de contactprivacy-instelling van de ontvanger, net als elke '
                            . 'andere Moodle-systeemnotificatie. '
                            . 'SCHRIJFACTIE - enkel op de write-service plaatsen, nooit op de leesservice.',
        'type'          => 'write',
        'capabilities'  => 'local/coursecohortinfo:sendnotification',
        'ajax'          => false,
    ],
    'local_coursecohortinfo_get_activity_description' => [
        'classname'     => 'local_coursecohortinfo\external\get_activity_description',
        'methodname'    => 'execute',
        'description'   => 'Geeft naam, beschrijving (HTML + platte tekst) en ingesloten afbeeldingen '
                            . '(base64) van een activiteit terug, rechtstreeks via databankquery en '
                            . 'get_file_storage() - omzeilt structureel het probleem dat '
                            . 'mod_lti_get_ltis_by_courses en pluginfile.php cohort-beperkte activiteiten '
                            . 'niet correct teruggeven, ongeacht viewhiddenactivities-capabilities.',
        'type'          => 'read',
        'capabilities'  => 'local/coursecohortinfo:view',
        'ajax'          => false,
    ],
];

// Optioneel: enkel invullen als je dit als eigen, losstaande service wil (in plaats
// van de functie toe te voegen aan je bestaande "moodleclassroomES"-service via de UI).
// Laat dit gerust staan als je de functie liever manueel toevoegt via
// Sitebeheer > Plugins > Webservices > Externe services.
$services = [
    'Cursus-cohort inschrijvingsinfo' => [
        'functions'       => ['local_coursecohortinfo_get_course_cohort_enrolments'],
        'restrictedusers' => 1, // Enkel expliciet toegelaten gebruikers.
        'enabled'         => 0, // Bewust standaard uitgeschakeld; zelf aanzetten in de UI.
        'shortname'       => 'local_coursecohortinfo',
    ],
];
