# local_coursecohortinfo — Changelog

| Versie | Wijziging |
|---|---|
| 2026072900 | Eerste versie: `get_course_cohort_enrolments` |
| 2026072901 | `get_available_users` toegevoegd (eerste versie, bulk `filter_user_list`) |
| 2026072902 | Groepen/cohorten (met naam) toegevoegd aan `get_available_users`-respons |
| 2026072903 | Contextvalidatie gecorrigeerd (cursus- i.p.v. modulecontext) |
| 2026072904 | Bulkfiltering vervangen door per-gebruiker `uservisible`-controle (cohort-plugin-beperking, zie documentatie §9.3) |
| 2026072905 | Leerkrachten/niet-bewerkende leerkrachten uitgefilterd uit de leerlingenlijst |
| 2026073000 | `get_courses_using_lti_type` toegevoegd |
| 2026073001 | `override_completion_status` toegevoegd (schrijffunctie, write-service); nieuwe capability `local/coursecohortinfo:overridecompletion` |
| 2026073002 | `get_completion_status_bulk` en `override_completion_status_bulk` toegevoegd (N→1 aanroepen) |
| 2026073003 | Betrouwbaarheidsfix: alle schrijffuncties valideren nu tegen de betrouwbare toegelaten-lijst i.p.v. blind te schrijven (loste "ghost record"-bug op bij onbestaande userid's, zie documentatie §9.4). `checklist`-veld toegevoegd aan alle betrokken responses. |
| 2026073004 | `get_completion_settings` en `set_completion_expected_date` toegevoegd |
| 2026080200 | Bugfix: `TypeError` in `rebuild_course_cache()` door ontbrekende `(int)`-cast (`strict_types`-gevoeligheid) |
| 2026080201 | `set_completion_expected_date` vervangen door `set_completion_settings` (kan nu ook `completionmode` instellen, met bescherming tegen inconsistente datum-zonder-voltooiing-combinatie); `likelyforgottendeadline`-logica in `get_completion_settings` gecorrigeerd |
| 2026080202 | `\core_completion\api::update_completion_date_event()`-aanroep toegevoegd aan `set_completion_settings` — loste op dat de Tijdlijn-weergave niet bijgewerkt werd (zie documentatie §9.5) |
| 2026080203 | `send_deadline_notification` toegevoegd; nieuwe capability `local/coursecohortinfo:sendnotification`; `db/messages.php` toegevoegd (berichtprovider `deadlinereminder`) |
| 2026080204 | E-mail uitgeschakeld (`MESSAGE_DISALLOWED`) voor `deadlinereminder` — voorkwam mislukte SMTP-pogingen op een omgeving zonder e-mailconfiguratie |
| 2026080300 | Poging tot fix van `db/messages.php`-constanten (`MESSAGE_DEFAULT_LOGGEDIN`/`MESSAGE_DEFAULT_LOGGEDOFF`) — **veroorzaakte een upgrade-crash**, deze constanten bleken volledig verwijderd in recente Moodle-versies |
| 2026080301 | Correctie: teruggezet naar `MESSAGE_DEFAULT_ENABLED` (de juiste, actuele constante, zie documentatie §9.6) |
| 2026080302 (niet-gepubliceerd tussenversie) | `db/events.php`, `classes/observer.php`, `classes/task/send_webhook.php`, `settings.php` toegevoegd: trigger-/webhooksysteem op `\core\event\course_module_updated`, asynchroon via ad-hoc taak, HMAC-ondertekend. Zie documentatie hoofdstuk 6. |
| 2026080401 | Webhook-payload uitgebreid met naam, beschrijving (HTML + platte tekst), actueel opgehaald op uitvoeringsmoment van de taak — generiek via de standaard `intro`/`introformat`-velden |
| 2026080402 | `get_activity_description` toegevoegd: naam/beschrijving/ingesloten afbeeldingen (base64) rechtstreeks via databankquery + `get_file_storage()`, omzeilt structureel de cohort-gevoeligheid van `mod_lti_get_ltis_by_courses`/`pluginfile.php` |
| 2026080403 | Bugfix: `get_area_files()` kreeg het verkeerde `component`-argument (`"lti"` i.p.v. `"mod_lti"`), waardoor `files` altijd leeg bleef ondanks correct werkende tekst (zie documentatie §9.9) |
| 2026080404 (opzijgezet, niet actief) | Kandidaat-fix: expliciete sectie-`uservisible`-controle toegevoegd aan `helper::get_allowed_students()` en `get_available_users`. **Teruggedraaid** nadat bleek dat het waargenomen "iedereen zichtbaar"-resultaat in het geteste geval een correcte uitkomst was (twee cohorten dekten toevallig exact de volledige klas), niet een bug. Zie `local_coursecohortinfo-sectiecheck-kandidaat-documentatie.md` voor de volledige analyse en de voorwaarde om dit later alsnog te heroverwegen. |
| 2026080405 | `get_available_users_for_instance` toegevoegd: lost het onderliggende "cmid onvindbaar bij sectie-brede cohort-beperking"-probleem op via een zichtbaarheids-ongevoelige databank-join, in plaats van de (afgewezen) sectiecheck-aanpak van 2026080404. Group/cohort-extractielogica verplaatst naar de gedeelde `helper`-klasse (was gedupliceerd in `get_available_users`). **Huidige, stabiele versie.** |

## Belangrijk bij een toekomstige wijziging aan `db/messages.php`

Wijzigingen aan de `defaults`-instelling worden **niet met terugwerkende kracht** herrekend
bij een gewone plugin-upgrade op een reeds bestaande installatie. Herbevestig de
standaardwaarde na elke wijziging via **Sitebeheer > Berichten > Instellingen meldingen >
Standaard berichtenvoorkeuren**.

## Belangrijk bij een toekomstige wijziging aan de "toegelaten leerlingen"-logica

Zie `local_coursecohortinfo-sectiecheck-kandidaat-documentatie.md` vóór je opnieuw een
sectie-brede `uservisible`-controle overweegt toe te voegen aan `helper::get_allowed_students()`
— dat pad is al eens geprobeerd en bewust teruggedraaid. `get_available_users_for_instance`
(versie 2026080405) loste het onderliggende, echte probleem (cmid-opzoeking) op een andere,
gerichtere manier op.
