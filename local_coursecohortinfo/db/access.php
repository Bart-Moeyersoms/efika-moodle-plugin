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
 * Capability definitions for local_coursecohortinfo.
 *
 * @package    local_coursecohortinfo
 * @copyright  2026 Efika / Dalton Gent
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$capabilities = [
    // Bewust een eigen, aparte capability - niet hergebruiken van een bestaande brede
    // capability zoals moodle/course:managegroups. Standaard aan niemand toegekend
    // (archetypes leeg); moet expliciet toegevoegd worden aan de gewenste rol.
    'local/coursecohortinfo:view' => [
        'captype'      => 'read',
        'contextlevel' => CONTEXT_COURSE,
        'archetypes'   => [],
    ],

    // Schrijf-capability voor override_completion_status. Enkel toekennen aan
    // de write-rol (MoodleClassroom_Write), NOOIT aan de leesrol.
    'local/coursecohortinfo:overridecompletion' => [
        'captype'      => 'write',
        'contextlevel' => CONTEXT_COURSE,
        'archetypes'   => [],
    ],

    // Schrijf-capability voor send_deadline_notification. Enkel toekennen
    // aan de write-rol.
    'local/coursecohortinfo:sendnotification' => [
        'captype'      => 'write',
        'contextlevel' => CONTEXT_COURSE,
        'archetypes'   => [],
    ],
];
