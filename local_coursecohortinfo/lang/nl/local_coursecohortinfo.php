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
 * Nederlandse taalstrings voor local_coursecohortinfo.
 *
 * @package    local_coursecohortinfo
 * @copyright  2026 Efika / Dalton Gent
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['pluginname'] = 'Cursus-cohort inschrijvingsinfo';
$string['coursecohortinfo:view'] = 'Zien welke cohorten als inschrijvingsmethode in een cursus gebruikt worden';
$string['coursecohortinfo:overridecompletion'] = 'Voltooiingsstatus van een leerling overschrijven zonder zelf ingeschreven te zijn';
$string['coursecohortinfo:sendnotification'] = 'Systeemnotificaties versturen naar leerlingen';
$string['completionnotenabledforactivity'] = 'Voltooiing volgen staat niet aan voor deze specifieke activiteit';
$string['usernotallowed'] = 'Deze gebruiker is niet gevonden, niet ingeschreven, of niet toegelaten tot deze activiteit: {$a}';
$string['messageprovider:deadlinereminder'] = 'Meldingen over deadlines/opdrachten (LTI-koppeling)';
$string['webhookurl'] = 'Webhook-URL';
$string['webhookurl_desc'] = 'De URL waarnaartoe een melding gestuurd wordt telkens een gevolgde activiteit (zie hieronder) wordt bewerkt en opgeslagen. Leeg laten om de webhook volledig uit te schakelen.';
$string['webhooksecret'] = 'Geheime ondertekeningssleutel';
$string['webhooksecret_desc'] = 'Wordt gebruikt om elke webhook-aanroep te ondertekenen (HMAC-SHA256, header X-Moodle-Signature), zodat de ontvangende toepassing kan verifiëren dat de melding echt van deze Moodle-omgeving komt. Leeg laten om zonder ondertekening te versturen (niet aangeraden).';
$string['webhookmodules'] = 'Gevolgde moduletypes';
$string['webhookmodules_desc'] = 'Kommagescheiden lijst van moduletypes (bv. "lti" of "lti,assign") waarvoor een webhook gestuurd wordt bij een wijziging. Enkel activiteiten van deze types triggeren de webhook.';
$string['webhookfailed'] = 'Versturen van de webhook is mislukt: {$a}';
