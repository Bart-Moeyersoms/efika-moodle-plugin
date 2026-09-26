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
 * Ad-hoc task: send_webhook.
 *
 * Verstuurt de effectieve HTTP POST-webhook, los van het opslagmoment zelf.
 * Moodle's eigen taaksysteem herprobeert automatisch (met oplopende
 * vertraging) als execute() een uitzondering gooit - vandaar dat een
 * mislukte HTTP-aanroep hieronder bewust een exception gooit in plaats van
 * de fout stil te negeren.
 *
 * De payload bevat, naast de basisidentificatie (cmid/courseid/modname),
 * ook de actuele naam en beschrijving van de activiteit (opgehaald op het
 * moment van uitvoering, generiek via de standaard intro/introformat-
 * velden) - zodat de ontvangende toepassing hiervoor geen aparte
 * API-aanroep hoeft te doen.
 *
 * @package    local_coursecohortinfo
 * @copyright  2026 Efika / Dalton Gent
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

declare(strict_types=1);

namespace local_coursecohortinfo\task;

defined('MOODLE_INTERNAL') || die();

class send_webhook extends \core\task\adhoc_task {

    /**
     * Voert de taak uit.
     */
    public function execute(): void {
        global $DB;

        $data = $this->get_custom_data();

        $webhookurl = (string) get_config('local_coursecohortinfo', 'webhookurl');
        if ($webhookurl === '') {
            // Ondertussen uitgeschakeld in de instellingen - niets meer te doen.
            return;
        }

        $secret = (string) get_config('local_coursecohortinfo', 'webhooksecret');

        $payloaddata = [
            'event'       => $data->eventname,
            'cmid'        => (int) $data->cmid,
            'courseid'    => (int) $data->courseid,
            'modname'     => $data->modname,
            'userid'      => (int) $data->userid,
            'timecreated' => (int) $data->timecreated,
        ];

        // Naam en beschrijving toevoegen, actueel opgehaald op het moment dat
        // deze taak uitgevoerd wordt (niet het moment van opslaan zelf) - zo
        // hoeft de ontvangende toepassing hiervoor geen aparte API-aanroep
        // te doen. Generiek: elk standaard Moodle-activiteittype (lti,
        // assign, page, ...) bewaart de beschrijving in de conventionele
        // 'intro'/'introformat'-velden van zijn eigen instantietabel.
        try {
            [, $cm] = get_course_and_cm_from_cmid((int) $data->cmid);
            $instance = $DB->get_record(
                $cm->modname,
                ['id' => $cm->instance],
                'name, intro, introformat',
                IGNORE_MISSING
            );
            if ($instance !== false) {
                $payloaddata['name'] = (string) $instance->name;
                $payloaddata['description'] = (string) $instance->intro;
                $payloaddata['descriptionformat'] = (int) $instance->introformat;
                // Extra, makkelijk te vergelijken platte-tekstversie, zonder
                // HTML-opmaak - handig als de ontvangende toepassing enkel
                // een eenvoudige tekstvergelijking wil doen.
                $payloaddata['descriptionplaintext'] = html_to_text($instance->intro, 0, false);
            }
        } catch (\Throwable $e) {
            // Naam/beschrijving ophalen is een bonus, geen vereiste - een
            // fout hier mag de rest van de webhook niet blokkeren. De
            // ontvangende toepassing kan dit altijd nog apart opvragen via
            // get_completion_settings/core_course_get_contents.
            debugging('local_coursecohortinfo send_webhook: kon naam/beschrijving niet ophalen: '
                . $e->getMessage(), DEBUG_DEVELOPER);
        }

        $payload = json_encode($payloaddata);

        $headers = ['Content-Type: application/json'];
        if ($secret !== '') {
            // HMAC-ondertekening: laat de ontvangende toepassing verifiëren
            // dat de payload echt van deze Moodle-omgeving komt en
            // onderweg niet gewijzigd is.
            $signature = hash_hmac('sha256', $payload, $secret);
            $headers[] = 'X-Moodle-Signature: sha256=' . $signature;
        }

        $curl = new \curl();
        $curl->setHeader($headers);
        $response = $curl->post($webhookurl, $payload, [
            'CURLOPT_TIMEOUT'        => 10,
            'CURLOPT_CONNECTTIMEOUT' => 5,
        ]);

        $info = $curl->get_info();
        $httpcode = (int) ($info['http_code'] ?? 0);

        if ($httpcode < 200 || $httpcode >= 300) {
            // Gooi bewust een fout, zodat Moodle's taaksysteem dit
            // automatisch herprobeert in plaats van de mislukking stil te
            // negeren.
            throw new \moodle_exception(
                'webhookfailed', 'local_coursecohortinfo', '', null,
                'HTTP ' . $httpcode . ' - ' . substr((string) $response, 0, 500)
            );
        }
    }
}
