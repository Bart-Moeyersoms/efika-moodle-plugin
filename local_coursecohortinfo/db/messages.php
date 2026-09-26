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
 * Message provider definitions for local_coursecohortinfo.
 *
 * Zonder 'capability'-sleutel: zichtbaar/beschikbaar voor alle gebruikers in
 * hun berichtvoorkeuren, geen aparte capability vereist om het te ontvangen.
 *
 * @package    local_coursecohortinfo
 * @copyright  2026 Efika / Dalton Gent
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$messageproviders = [
    'deadlinereminder' => [
        // Geen 'capability'-sleutel: voor iedereen zichtbaar/ontvangbaar.
        //
        // E-mail bewust volledig uitgeschakeld (MESSAGE_DISALLOWED) voor dit
        // berichttype - onafhankelijk van de persoonlijke voorkeur van de
        // ontvanger. Zolang SMTP op deze omgeving niet is ingesteld, zou
        // Moodle anders bij elke notificatie een mislukte e-mailpoging
        // proberen, wat het versturen liet falen. Enkel de schermmelding
        // (popup/berichten-icoon) blijft actief.
        //
        // BELANGRIJK: MESSAGE_DEFAULT_LOGGEDIN en MESSAGE_DEFAULT_LOGGEDOFF
        // zijn in recente Moodle-versies volledig VERWIJDERD (niet enkel
        // verouderd) - zie lib/upgrade.txt. De vervangende, huidige
        // constante is MESSAGE_DEFAULT_ENABLED (één vlag, geen onderscheid
        // meer tussen ingelogd/uitgelogd).
        'defaults' => [
            'popup' => MESSAGE_PERMITTED + MESSAGE_DEFAULT_ENABLED,
            'email' => MESSAGE_DISALLOWED,
        ],
    ],
];
