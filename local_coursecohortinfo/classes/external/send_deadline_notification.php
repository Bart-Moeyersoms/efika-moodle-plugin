<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY with the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * External function: send_deadline_notification.
 *
 * Stuurt een persoonlijke SYSTEEMNOTIFICATIE (geen instant message) naar één
 * specifieke leerling - bv. om een individuele verlenging/heropening van een
 * LTI-opdracht te melden. In tegenstelling tot
 * core_message_send_instant_messages (persoonlijk bericht, geblokkeerd door
 * de contactprivacy-instelling van de ontvanger), gebruikt dit de
 * \core\message\message-klasse met notification=1, wat de contactprivacy-
 * check bewust omzeilt - net zoals elke andere systeemnotificatie in Moodle
 * (bv. "je cijfer is klaar").
 *
 * Controleert VOORAF of de opgegeven userid effectief ingeschreven en
 * toegelaten is tot de opgegeven activiteit (dezelfde betrouwbare checklist
 * als de completion-functies) - een notificatie wordt geweigerd voor wie
 * daar niet aan voldoet.
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

use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_multiple_structure;
use core_external\external_single_structure;
use core_external\external_value;
use context_course;
use local_coursecohortinfo\local\helper;

class send_deadline_notification extends external_api {

    /**
     * Parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'cmid'    => new external_value(PARAM_INT, 'Course module ID - voor de toegelaten-controle en de link'),
            'userid'  => new external_value(PARAM_INT, 'User ID van de leerling die de notificatie moet ontvangen'),
            'subject' => new external_value(PARAM_TEXT, 'Onderwerp van de notificatie'),
            'message' => new external_value(PARAM_RAW, 'Inhoud van de notificatie (platte tekst)'),
        ]);
    }

    /**
     * Execute.
     *
     * @param int $cmid
     * @param int $userid
     * @param string $subject
     * @param string $message
     * @return array
     */
    public static function execute(int $cmid, int $userid, string $subject, string $message): array {
        global $DB;

        $params = self::validate_parameters(self::execute_parameters(), [
            'cmid'    => $cmid,
            'userid'  => $userid,
            'subject' => $subject,
            'message' => $message,
        ]);
        $cmid = $params['cmid'];
        $userid = $params['userid'];
        $subject = $params['subject'];
        $message = $params['message'];

        [$course, $cm] = get_course_and_cm_from_cmid($cmid);

        $coursecontext = context_course::instance($course->id);
        self::validate_context($coursecontext);
        require_capability('local/coursecohortinfo:sendnotification', $coursecontext);

        // Zelfde betrouwbare checklist als bij de completion-functies: enkel
        // écht ingeschreven EN toegelaten leerlingen mogen een notificatie
        // krijgen, ongeacht welke userid wordt opgegeven.
        $enrolledstudents = helper::get_enrolled_students($coursecontext);
        $allowedstudents = helper::get_allowed_students($course, $cmid, $enrolledstudents);
        if (!isset($allowedstudents[$userid])) {
            return [
                'success'   => false,
                'error'     => 'Deze userid is niet gevonden, niet ingeschreven, of niet toegelaten '
                                . 'tot deze activiteit - geen notificatie verstuurd.',
                'checklist' => array_map('intval', array_keys($allowedstudents)),
            ];
        }

        $touser = $DB->get_record('user', ['id' => $userid], '*', MUST_EXIST);
        $courseurl = new \moodle_url('/mod/' . $cm->modname . '/view.php', ['id' => $cmid]);

        $notification = new \core\message\message();
        $notification->component = 'local_coursecohortinfo';
        $notification->name = 'deadlinereminder';
        $notification->userfrom = \core_user::get_noreply_user();
        $notification->userto = $touser;
        $notification->subject = $subject;
        $notification->fullmessage = $message;
        $notification->fullmessageformat = FORMAT_PLAIN;
        $notification->fullmessagehtml = nl2br(s($message));
        $notification->smallmessage = $subject;
        // Dit is de kern: notification=1 maakt dit een systeemnotificatie,
        // wat de contactprivacy-instelling van de ontvanger omzeilt - in
        // tegenstelling tot een gewoon persoonlijk bericht.
        $notification->notification = 1;
        $notification->contexturl = $courseurl->out(false);
        $notification->contexturlname = $cm->name;

        $result = false;
        $senderror = '';
        try {
            $result = message_send($notification);
        } catch (\Throwable $e) {
            $senderror = $e->getMessage();
        }

        return [
            'success'   => (bool) $result,
            'error'     => $result ? '' : ($senderror ?: 'Versturen is mislukt (onbekende reden - controleer Moodle-logs).'),
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
            'success'   => new external_value(PARAM_BOOL, 'Of de notificatie effectief verstuurd is'),
            'error'     => new external_value(PARAM_TEXT, 'Foutmelding indien niet gelukt, anders leeg'),
            'checklist' => new external_multiple_structure(
                new external_value(PARAM_INT, 'User ID'),
                'Volledige, actuele lijst userid\'s die op dit moment toegelaten zijn tot de activiteit.'
            ),
        ]);
    }
}
