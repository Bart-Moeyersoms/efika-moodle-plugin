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
 * Event observer.
 *
 * @package    local_coursecohortinfo
 * @copyright  2026 Efika / Dalton Gent
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

declare(strict_types=1);

namespace local_coursecohortinfo;

defined('MOODLE_INTERNAL') || die();

class observer {

    /**
     * Reageert op \core\event\course_module_updated. Zet enkel een lichte
     * ad-hoc taak in de wachtrij - de effectieve HTTP-aanroep gebeurt apart
     * via cron, zodat een trage/onbereikbare externe server het opslaan van
     * de leerkracht nooit vertraagt of laat mislukken.
     *
     * BELANGRIJK: dit event vuurt bij ELKE wijziging aan de
     * activiteitinstellingen (naam, beschrijving, data, ...), niet specifiek
     * enkel bij een beschrijvingswijziging - Moodle's event bevat geen
     * "welk veld precies gewijzigd is"-detail. De ontvangende toepassing
     * moet zelf vergelijken met de vorige, gekende waarde om te bepalen of
     * het effectief de beschrijving was.
     *
     * @param \core\event\course_module_updated $event
     */
    public static function course_module_updated(\core\event\course_module_updated $event): void {
        $modname = $event->other['modulename'] ?? null;
        if ($modname === null) {
            return;
        }

        $watchedraw = (string) get_config('local_coursecohortinfo', 'webhookmodules');
        $watchedlist = array_filter(array_map('trim', explode(',', $watchedraw)));
        if (empty($watchedlist) || !in_array($modname, $watchedlist, true)) {
            return; // Dit moduletype wordt niet gevolgd.
        }

        $webhookurl = (string) get_config('local_coursecohortinfo', 'webhookurl');
        if ($webhookurl === '') {
            return; // Geen webhook geconfigureerd, niets te doen.
        }

        $task = new \local_coursecohortinfo\task\send_webhook();
        $task->set_custom_data([
            'eventname'   => $event->eventname,
            'cmid'        => $event->objectid,
            'courseid'    => $event->courseid,
            'modname'     => $modname,
            'userid'      => $event->userid,
            'timecreated' => $event->timecreated,
        ]);
        \core\task\manager::queue_adhoc_task($task);
    }
}
