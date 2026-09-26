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
 * External function: get_activity_description.
 *
 * Geeft de naam en beschrijving van een activiteit terug, samen met alle
 * daarin ingesloten afbeeldingen (als base64).
 *
 * WAAROM DEZE FUNCTIE BESTAAT: kernfuncties zoals mod_lti_get_ltis_by_courses
 * (voor de tekst) en pluginfile.php (voor ingesloten afbeeldingen) passen
 * Moodle's volledige beschikbaarheidscontrole toe, inclusief cohort-
 * voorwaarden. Bevestigd: voor een cohort-beperkte activiteit geeft
 * mod_lti_get_ltis_by_courses stilzwijgend een lege lijst terug (geen
 * onderscheid tussen "bestaat niet" en "mag niet gezien worden"), en
 * pluginfile.php weigert de afbeelding met een requireloginerror -
 * ongeacht welke capabilities het aanroepende account heeft (bevestigd met
 * moodle/course:viewhiddenactivities EN de andere viewhidden*-capabilities
 * actief, zonder effect).
 *
 * DEZE FUNCTIE OMZEILT DAT STRUCTUREEL, NIET VIA EEN CAPABILITY: ze haalt
 * de tekst rechtstreeks via een databankquery op (geen availability-check
 * ooit aangeroepen) en de afbeeldingen via get_file_storage() - Moodle's
 * rauwe bestandsopslag-laag, die per ontwerp GEEN zichtbaarheids-/
 * beschikbaarheidscontrole uitvoert (dat gebeurt bij het RENDEREN van een
 * pagina, niet bij het opslaan/ophalen van bestanden zelf).
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
use context_module;

class get_activity_description extends external_api {

    /** Maximale totale grootte (bytes) aan bestanden die meegegeven wordt - vangnet tegen te grote responses. */
    const MAX_TOTAL_BYTES = 8 * 1024 * 1024; // 8 MB

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

        $params = self::validate_parameters(self::execute_parameters(), ['cmid' => $cmid]);
        $cmid = $params['cmid'];

        [$course, $cm] = get_course_and_cm_from_cmid($cmid);

        // Bewust tegen de CURSUScontext valideren, niet de modulecontext -
        // zelfde reden als bij onze andere functies (zie helper.php/
        // get_available_users): validatie tegen de modulecontext zou zelf
        // een beschikbaarheidscontrole triggeren die we net willen omzeilen.
        $coursecontext = context_course::instance($course->id);
        self::validate_context($coursecontext);
        require_capability('local/coursecohortinfo:view', $coursecontext);

        // Rechtstreekse databankquery - geen enkele Moodle-availability-
        // functie wordt hier ooit aangeroepen.
        $instance = $DB->get_record(
            $cm->modname,
            ['id' => $cm->instance],
            'name, intro, introformat',
            MUST_EXIST
        );

        // context_module::instance() hier is enkel een sleutel voor de
        // bestandsopslag-lookup hieronder - we roepen er NOOIT
        // validate_context()/require_login() op aan, dus dit triggert geen
        // beschikbaarheidscontrole.
        $modcontext = context_module::instance($cmid);
        $fs = get_file_storage();
        // BELANGRIJK: het component-argument moet het frankenstyle-formaat
        // zijn ("mod_lti"), niet enkel de moduletype-naam ("lti"). Bij een
        // verkeerd component geeft get_area_files() stilzwijgend een lege
        // array terug - geen foutmelding, vandaar dat dit initieel niet
        // opviel.
        $component = 'mod_' . $cm->modname;
        $storedfiles = $fs->get_area_files($modcontext->id, $component, 'intro', 0, 'filename', false);

        $files = [];
        $totalbytes = 0;
        $truncated = false;
        foreach ($storedfiles as $storedfile) {
            $filesize = $storedfile->get_filesize();
            if ($totalbytes + $filesize > self::MAX_TOTAL_BYTES) {
                $truncated = true;
                continue; // Vangnet: te grote bijlagen overslaan i.p.v. een enorme respons te bouwen.
            }
            $totalbytes += $filesize;
            $files[] = [
                'filename' => $storedfile->get_filename(),
                'mimetype' => (string) $storedfile->get_mimetype(),
                'filesize' => (int) $filesize,
                'base64'   => base64_encode($storedfile->get_content()),
            ];
        }

        return [
            'cmid'                  => $cmid,
            'name'                  => (string) $instance->name,
            'description'           => (string) $instance->intro,
            'descriptionformat'     => (int) $instance->introformat,
            'descriptionplaintext'  => html_to_text((string) $instance->intro, 0, false),
            'files'                 => $files,
            'filestruncated'        => $truncated,
        ];
    }

    /**
     * Returns.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'cmid'                 => new external_value(PARAM_INT, 'Course module ID'),
            'name'                 => new external_value(PARAM_TEXT, 'Naam van de activiteit'),
            'description'          => new external_value(PARAM_RAW, 'Ruwe beschrijving (HTML), met @@PLUGINFILE@@-tokens niet vervangen'),
            'descriptionformat'    => new external_value(PARAM_INT, 'Tekstformaat van de beschrijving (FORMAT_*)'),
            'descriptionplaintext' => new external_value(PARAM_RAW, 'Beschrijving zonder HTML-opmaak'),
            'files' => new external_multiple_structure(
                new external_single_structure([
                    'filename' => new external_value(PARAM_TEXT, 'Bestandsnaam'),
                    'mimetype' => new external_value(PARAM_TEXT, 'MIME-type'),
                    'filesize' => new external_value(PARAM_INT, 'Bestandsgrootte in bytes'),
                    'base64'   => new external_value(PARAM_RAW, 'Bestandsinhoud, base64-gecodeerd'),
                ]),
                'Bestanden ingesloten in de beschrijving (bv. afbeeldingen)'
            ),
            'filestruncated' => new external_value(
                PARAM_BOOL,
                'True als één of meer bestanden zijn weggelaten wegens de maximale totale grootte (8 MB)'
            ),
        ]);
    }
}
