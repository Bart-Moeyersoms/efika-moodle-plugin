# local_coursecohortinfo — Changelog

| Versie | Datum | Wijziging |
|---|---|---|
| 2026072900 | 29 jul 2026 | Eerste versie: `get_course_cohort_enrolments` |
| 2026072901 | 29 jul 2026 | `get_available_users` toegevoegd (eerste versie, bulk `filter_user_list`) |
| 2026072902 | 29 jul 2026 | Groepen/cohorten (met naam) toegevoegd aan `get_available_users`-respons |
| 2026072903 | 29 jul 2026 | Contextvalidatie gecorrigeerd (cursus- i.p.v. modulecontext) |
| 2026072904 | 29 jul 2026 | Bulkfiltering vervangen door per-gebruiker `uservisible`-controle (cohort-plugin-beperking, zie documentatie §8.3) |
| 2026072905 | 29 jul 2026 | Leerkrachten/niet-bewerkende leerkrachten uitgefilterd uit de leerlingenlijst |
| 2026073000 | 30 jul 2026 | `get_courses_using_lti_type` toegevoegd |
| 2026073001 | 31 jul 2026 | `override_completion_status` toegevoegd (schrijffunctie, write-service); nieuwe capability `local/coursecohortinfo:overridecompletion` |
| 2026073002 | 31 jul 2026 | `get_completion_status_bulk` en `override_completion_status_bulk` toegevoegd (N→1 aanroepen) |
| 2026073003 | 31 jul 2026 | Betrouwbaarheidsfix: alle schrijffuncties valideren nu tegen de betrouwbare toegelaten-lijst i.p.v. blind te schrijven (loste "ghost record"-bug op bij onbestaande userid's, zie documentatie §8.4). `checklist`-veld toegevoegd aan alle betrokken responses. |
| 2026073004 | 31 jul 2026 | `get_completion_settings` en `set_completion_expected_date` toegevoegd |
| 2026080200 | 2 aug 2026 | Bugfix: `TypeError` in `rebuild_course_cache()` door ontbrekende `(int)`-cast (`strict_types`-gevoeligheid, zie documentatie §8.2-gerelateerd) |
| 2026080201 | 2 aug 2026 | `set_completion_expected_date` vervangen door `set_completion_settings` (kan nu ook `completionmode` instellen, met bescherming tegen inconsistente datum-zonder-voltooiing-combinatie); `likelyforgottendeadline`-logica in `get_completion_settings` gecorrigeerd |
| 2026080202 | 2 aug 2026 | `\core_completion\api::update_completion_date_event()`-aanroep toegevoegd aan `set_completion_settings` — loste op dat de Tijdlijn-weergave niet bijgewerkt werd (zie documentatie §8.5) |
| 2026080203 | 2 aug 2026 | `send_deadline_notification` toegevoegd; nieuwe capability `local/coursecohortinfo:sendnotification`; `db/messages.php` toegevoegd (berichtprovider `deadlinereminder`) |
| 2026080204 | 2 aug 2026 | E-mail uitgeschakeld (`MESSAGE_DISALLOWED`) voor `deadlinereminder` — voorkwam mislukte SMTP-pogingen op een omgeving zonder e-mailconfiguratie |
| 2026080300 | 3 aug 2026 | Poging tot fix van `db/messages.php`-constanten (`MESSAGE_DEFAULT_LOGGEDIN`/`MESSAGE_DEFAULT_LOGGEDOFF`) — **veroorzaakte een upgrade-crash**, deze constanten bleken volledig verwijderd in recente Moodle-versies |
| 2026080301 | 3 aug 2026 | Correctie: teruggezet naar `MESSAGE_DEFAULT_ENABLED` (de juiste, actuele constante — zie documentatie §8.6). **Huidige, stabiele versie.** |

## Belangrijk bij een toekomstige wijziging aan `db/messages.php`

Wijzigingen aan de `defaults`-instelling worden **niet met terugwerkende kracht** herrekend
bij een gewone plugin-upgrade op een reeds bestaande installatie. Herbevestig de
standaardwaarde na elke wijziging via **Sitebeheer > Berichten > Instellingen meldingen >
Standaard berichtenvoorkeuren**.
