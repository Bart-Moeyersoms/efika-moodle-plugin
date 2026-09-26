# local_coursecohortinfo — Documentatie

**Moodle-plugintype:** Local plugin
**Component:** `local_coursecohortinfo`
**Huidige versie:** 2026080301
**Vereist:** Moodle 4.3+ (getest en in productie op Moodle 5.2.1)
**Doel:** ondersteunt een externe LTI-toepassing (classroom-validator) met veilige, beperkte
webservice-functies bovenop wat Moodle's kernfuncties niet (correct) aanbieden.

---

## Inhoud

1. [Waarom deze plugin bestaat](#1-waarom-deze-plugin-bestaat)
2. [Architectuur en beveiligingsprincipes](#2-architectuur-en-beveiligingsprincipes)
3. [Installatie](#3-installatie)
4. [Functieoverzicht](#4-functieoverzicht)
5. [Functiereferentie](#5-functiereferentie)
6. [Gedeelde hulpklasse: helper](#6-gedeelde-hulpklasse-helper)
7. [Notificatiesysteem: deadlinereminder](#7-notificatiesysteem-deadlinereminder)
8. [Gekende Moodle-eigenaardigheden die deze plugin omzeilt](#8-gekende-moodle-eigenaardigheden-die-deze-plugin-omzeilt)
9. [Gekende beperkingen](#9-gekende-beperkingen)
10. [Testen](#10-testen)

---

## 1. Waarom deze plugin bestaat

Voor de koppeling tussen Moodle en de externe LTI-toepassing (classroom-validator) waren een
aantal gegevens/acties nodig die Moodle's eigen, standaard webservice-functies **niet, niet
betrouwbaar, of niet veilig genoeg** aanbieden:

| Behoefte | Waarom de standaardfunctie niet volstond |
|---|---|
| Welk cohort is aan een cursus gekoppeld? | Geen enkele kernfunctie legt deze koppeling bloot; `core_enrol_get_course_enrolment_methods` toont enkel methodes die een gebruiker zelf kan initiëren (niet Sitegroepsynchronisatie). |
| Wie mag een activiteit effectief zien? | `\core_availability\info_module::filter_user_list()` filtert enkel voorwaarden waarvoor de betrokken availability-plugin dit expliciet ondersteunt — bevestigd dat `availability_cohort` dit niet doet, waardoor cohort-gebaseerde beperkingen genegeerd werden. |
| Voltooiingsstatus van een hele klas lezen/aanpassen | Kernfuncties werken per individuele leerling (N aanroepen voor een klas). |
| Voltooiingsstatus overschrijven zonder zelf ingeschreven te zijn | Moodle's kernfunctie vereist structureel dat het aanroepende account zelf ingeschreven is in de cursus (voltooiing is gegevensmodel-matig aan een inschrijving gekoppeld). |
| "Verwacht voltooid op" (deadline) lezen/instellen, ook als er nog geen voltooiingsvoorwaarde bestaat | Geen enkele kernfunctie legt dit veld bloot; nodig omdat leerkrachten dit bij het aanmaken van een LTI-opdracht soms vergeten in te stellen. |
| Een leerling persoonlijk informeren (bv. individuele verlenging) | Persoonlijke kalendergebeurtenissen voor een andere gebruiker zijn sinds een beveiligingsfix (MSA-22-0002) niet meer mogelijk; `core_message_send_instant_messages` wordt geblokkeerd door de contactprivacy-instelling van de ontvanger. |

## 2. Architectuur en beveiligingsprincipes

- **Least privilege, strikt gescheiden lees/schrijf.** Er zijn twee aparte Moodle-accounts,
  rollen en webservices: een **lees**-service (`moodleclassroomES`) en een **write**-service
  (`moodleclassroomES-write`). Functies die enkel lezen staan nooit op de write-service en
  omgekeerd.
- **Eigen, specifieke capabilities per functie(groep)** — nooit een bestaande, bredere
  capability hergebruikt tenzij die semantisch exact overeenkomt (bv.
  `moodle/course:manageactivities` voor het bewerken van activiteitinstellingen).
  Standaard aan niemand toegekend; moet expliciet aan de juiste rol toegevoegd worden.
- **Contextvalidatie tegen de cursuscontext, niet de modulecontext.** Validatie tegen een
  modulecontext triggert in Moodle een `require_login`-achtige controle die zou vereisen dat
  het *aanroepende* account zelf aan de Toegang beperken-voorwaarden van die ene activiteit
  voldoet — net wat een rapporterend/schrijvend service-account moet kunnen omzeilen.
- **Eén betrouwbare "toegelaten leerlingen"-checklist, overal hergebruikt.** Elke functie die
  met individuele leerlingen werkt, berekent zelf (via de gedeelde `helper`-klasse) wie écht
  ingeschreven én tot de activiteit toegelaten is — nooit gebaseerd op wat de aanroeper zelf
  meegeeft. Dit voorkwam een reëel probleem: `completion_info::update_state()` accepteerde
  aanvankelijk blindelings elke userid (ook onbestaande), zonder foutmelding.
- **Geparametriseerde SQL overal**, geen ruwe stringconcatenatie van gebruikersinvoer.
- **Hergebruik van Moodle's eigen bedrijfslogica waar mogelijk.** Bijvoorbeeld:
  `override_completion_status` roept dezelfde `completion_info::update_state()`-methode aan
  die Moodle's eigen kernfunctie ook gebruikt (dus dezelfde events, cache-invalidatie en
  cursusvoltooiing-herberekening) — enkel de contextvalidatie is aangepast, niet de
  onderliggende logica.

## 3. Installatie

Zie de aparte handleiding (`Moodle-Entra-documentatie.md`, hoofdstuk 12) voor de volledige
stap-voor-stap procedure (uitpakken, back-up van de oude map, verplaatsen, rechten,
Sitebeheer-upgrade). Kernpunten:

1. Plaats de map op `<moodle>/local/coursecohortinfo/`, eigenaar `www-data:www-data`.
2. Bevestig de upgrade in Sitebeheer.
3. Voeg elke gewenste functie toe aan de juiste service (lees- of write-service — zie
   tabel in hoofdstuk 4).
4. Ken de bijhorende capability toe aan de juiste rol.

## 4. Functieoverzicht

| Functienaam | Type | Service | Capability |
|---|---|---|---|
| `local_coursecohortinfo_get_course_cohort_enrolments` | read | lees | `local/coursecohortinfo:view` |
| `local_coursecohortinfo_get_available_users` | read | lees | `local/coursecohortinfo:view` |
| `local_coursecohortinfo_get_courses_using_lti_type` | read | lees | `local/coursecohortinfo:view` |
| `local_coursecohortinfo_get_completion_status_bulk` | read | lees | `local/coursecohortinfo:view` |
| `local_coursecohortinfo_get_completion_settings` | read | lees | `local/coursecohortinfo:view` |
| `local_coursecohortinfo_override_completion_status` | write | write | `local/coursecohortinfo:overridecompletion` |
| `local_coursecohortinfo_override_completion_status_bulk` | write | write | `local/coursecohortinfo:overridecompletion` |
| `local_coursecohortinfo_set_completion_settings` | write | write | `moodle/course:manageactivities` |
| `local_coursecohortinfo_send_deadline_notification` | write | write | `local/coursecohortinfo:sendnotification` |

## 5. Functiereferentie

### 5.1 `get_course_cohort_enrolments(courseid)`

Geeft alle cohort-gebaseerde inschrijvingsmethodes (Sitegroepsynchronisatie) van een cursus
terug.

**Parameters:** `courseid` (int)

**Returns:** lijst van `{enrolid, courseid, cohortid, cohortname, cohortidnumber, groupid,
roleid, status}`

### 5.2 `get_available_users(cmid)`

Geeft de effectieve lijst ingeschreven **leerlingen** (leerkrachten uitgesloten) terug die een
specifieke activiteit mogen zien, plus de betrokken groepen/cohorten (met naam).

**Kernmethode:** per leerling apart `get_fast_modinfo($course, $userid)->get_cm($cmid)->uservisible`
— bewust **niet** de snellere bulkmethode `filter_user_list()` (zie hoofdstuk 8.3).

**Parameters:** `cmid` (int)

**Returns:** `{cmid, totalenrolled, totalvisible, users: [{id, fullname, email}], groups:
[{id, name}], cohorts: [{id, name, idnumber}]}`

### 5.3 `get_courses_using_lti_type(ltitypeid)`

Geeft de cursussen terug die minstens één activiteit van een specifiek, geregistreerd
LTI-tooltype (`mdl_lti_types.id`) bevatten. Voorkomt dat een client-tool alle cursussen op de
site moet doorzoeken.

**Parameters:** `ltitypeid` (int) — zoek dit ID op via `SELECT id, name FROM mdl_lti_types;`

**Returns:** lijst van `{courseid, coursename}`

### 5.4 `get_completion_status_bulk(cmid)`

Geeft de voltooiingsstatus van **alle** toegelaten leerlingen voor een activiteit terug in
één aanroep, in plaats van N aparte aanroepen met `core_completion_get_activities_completion_status`.

**Parameters:** `cmid` (int)

**Returns:** `{cmid, completionenabled, users: [{userid, fullname, email, state,
timecompleted, overrideby}], checklist: [userid, ...]}`

### 5.5 `get_completion_settings(cmid)`

Geeft de "Voltooiingsvoorwaarden"-instellingen van een activiteit terug, inclusief of een
"Verwacht voltooid op"-datum ontbreekt — bruikbaar om te detecteren of dit bij het aanmaken
van een opdracht vergeten is.

**Parameters:** `cmid` (int)

**Returns:** `{cmid, completionenabledcourse, completionmode, completionview,
completionexpected, hasexpecteddate, completionpassgrade, likelyforgottendeadline}`

`likelyforgottendeadline` is `true` zodra er geen datum is ingesteld, **ongeacht**
`completionmode` (ook als voltooiing volgen zelf nog op 0/uit staat — net het duidelijkste
"vergeten"-geval).

### 5.6 `override_completion_status(userid, cmid, newstate)`

Overschrijft de voltooiingsstatus van één leerling. Weigert expliciet (foutmelding) als de
opgegeven userid niet in de betrouwbare toegelaten-lijst voorkomt.

**Parameters:** `userid` (int), `cmid` (int), `newstate` (int: 0=onvoltooid, 1=voltooid,
2=voltooid geslaagd, 3=voltooid niet geslaagd)

**Returns:** `{cmid, userid, state, timecompleted, overrideby, checklist: [userid, ...]}`

### 5.7 `override_completion_status_bulk(cmid, newstate, userids)`

Zelfde als hierboven, voor meerdere leerlingen in één aanroep. Een individuele mislukking (bv.
ongeldige userid) blokkeert de rest van de batch niet — elk resultaat wordt apart
gerapporteerd.

**Parameters:** `cmid` (int), `newstate` (int), `userids` (int[])

**Returns:** `{cmid, results: [{userid, success, state, error}], checklist: [userid, ...]}`

### 5.8 `set_completion_settings(cmid, completionmode, completionexpected)`

Stelt de voltooiingsmodus en/of de "Verwacht voltooid op"-datum in, samen of apart. Gebruik
`-1` voor een parameter om die ongewijzigd te laten.

Roept, naast het wegschrijven van de databankvelden, ook expliciet
`\core_completion\api::update_completion_date_event()` aan — dit is de stap die de
kalender-actiegebeurtenis (Tijdlijn-weergave) effectief aanmaakt/bijwerkt; een rechtstreekse
databankwijziging alleen doet dit niet (zie hoofdstuk 8.5).

**Bescherming:** als de uiteindelijke `completionmode` op 0 (uit) uitkomt, wordt
`completionexpected` altijd naar 0 geforceerd, ongeacht wat werd meegegeven — voorkomt de
inconsistente combinatie "datum ingesteld terwijl voltooiing volgen uitstaat".

**Parameters:** `cmid` (int), `completionmode` (int, default -1), `completionexpected` (int,
default -1)

**Returns:** `{cmid, completionmode, completionexpected, forcedexpecteddatetocleared}`

**Vereist:** "Voltooiing volgen" moet aanstaan op **cursusniveau**, anders weigert de functie
met een duidelijke foutmelding.

### 5.9 `send_deadline_notification(cmid, userid, subject, message)`

Stuurt een persoonlijke **systeemnotificatie** (geen instant message) naar één specifieke,
toegelaten leerling — bv. voor een individuele verlenging. Gebruikt `notification = 1`, wat
de contactprivacy-instelling van de ontvanger bewust omzeilt (net als elke andere
Moodle-systeemnotificatie zoals "je cijfer is klaar").

**Parameters:** `cmid` (int), `userid` (int), `subject` (string), `message` (string, platte
tekst)

**Returns:** `{success, error, checklist: [userid, ...]}`

E-mail is voor dit berichttype site-breed uitgeschakeld (zie hoofdstuk 7) — enkel de
schermmelding (popup/berichten-icoon) wordt gebruikt.

## 6. Gedeelde hulpklasse: helper

`classes/local/helper.php` — herbruikt door alle functies die met individuele leerlingen
werken.

- **`get_enrolled_students(context_course $coursecontext): array`** — alle actief
  ingeschreven gebruikers, leerkrachten/niet-bewerkende leerkrachten uitgesloten
  (archetype-gebaseerd: `editingteacher`, `teacher` — hernoemingsbestendig).
- **`get_allowed_students($course, int $cmid, array $enrolledusers): array`** — filtert tot
  enkel wie de activiteit effectief mag zien, via de per-gebruiker `uservisible`-berekening
  (zie hoofdstuk 8.3 voor waarom dit niet via de snellere bulkmethode gebeurt).

## 7. Notificatiesysteem: deadlinereminder

`db/messages.php` registreert een eigen berichttype (`deadlinereminder`), gebruikt door
`send_deadline_notification`.

- **Geen `capability`-sleutel** — zichtbaar/ontvangbaar voor alle gebruikers.
- **`'popup' => MESSAGE_PERMITTED + MESSAGE_DEFAULT_ENABLED`** — standaard aan, leerling kan
  het zelf uitzetten. (Zie hoofdstuk 8.6 voor de juiste constante — `MESSAGE_DEFAULT_LOGGEDIN`/
  `MESSAGE_DEFAULT_LOGGEDOFF` zijn in recente Moodle-versies volledig verwijderd.)
- **`'email' => MESSAGE_DISALLOWED`** — bewust uitgeschakeld, zolang er geen SMTP is
  ingesteld op deze omgeving (voorkomt mislukte verzendpogingen).

**Belangrijk:** wijzigingen aan `defaults` in `db/messages.php` worden **niet met
terugwerkende kracht** herrekend bij een gewone upgrade van een reeds geïnstalleerde plugin.
Voor een bestaande installatie moet de standaardwaarde herbevestigd worden via **Sitebeheer >
Berichten > Instellingen meldingen > Standaard berichtenvoorkeuren**.

Om dit te **verplichten** (leerling kan niet uitzetten): vervang `MESSAGE_PERMITTED +
MESSAGE_DEFAULT_ENABLED` door `MESSAGE_FORCED`, en herbevestig via hetzelfde beheerscherm.

## 8. Gekende Moodle-eigenaardigheden die deze plugin omzeilt

Dit hoofdstuk documenteert *waarom* de code bepaalde dingen doet zoals ze doet — waardevol bij
toekomstig onderhoud of een Moodle-upgrade.

### 8.1 Modulecontext-validatie vereist eigen inschrijving

`self::validate_context()` tegen een **modulecontext** triggert intern een
`require_login()`-achtige controle die vereist dat het aanroepende account zelf aan de
Toegang beperken-voorwaarden van die activiteit voldoet. Voor een rapporterend/schrijvend
service-account is dat net omgekeerd van wat nodig is. **Oplossing:** overal valideren tegen
de **cursuscontext**.

### 8.2 `moodle/course:view` geeft geen echte deelname

Bevestigd in Moodle's eigen documentatie: een account met enkel `moodle/course:view` kan een
cursus bekijken zonder ingeschreven te zijn, maar kan **niet** echt deelnemen — niet
beoordeeld worden, geen lid van groepen. Voltooiingsstatus is zo'n "echte deelname"-gegeven:
`completion_info::update_state()` vereist structureel dat het **aanroepende** account zelf
ingeschreven is in de cursus. Geen enkele capability lost dit op.

**Gevolg voor architectuur:** het write-service-account moet ingeschreven zijn in élke cursus
waar het moet kunnen schrijven. Aanbevolen aanpak: een dedicated, puur-lokale Moodle-sitegroep
(geen Entra-sync nodig) met het write-account als enige lid, gekoppeld via een tweede
Sitegroepsynchronisatie-inschrijvingsmethode (met een lege, onschadelijke rol) aan elke cursus
die de LTI-tool gebruikt.

### 8.3 `filter_user_list()` filtert niet alle voorwaarde-types

Moodle's bulkmethode `\core_availability\info_module::filter_user_list()` past een
voorwaarde enkel toe als de betrokken availability-plugin `is_applied_to_user_lists()`
expliciet implementeert. Bevestigd: `availability_cohort` doet dit niet — cohort-gebaseerde
beperkingen werden bij bulkfiltering genegeerd (iedereen leek "zichtbaar"). **Oplossing:**
per-gebruiker `get_fast_modinfo($course, $userid)->uservisible`, trager bij grote cursussen
maar altijd correct, ongeacht voorwaarde-type of EN/OF/NIET-combinatie.

### 8.4 Ghost-records bij een onbestaande userid

`completion_info::update_state()` accepteerde aanvankelijk blindelings elke userid, ook
niet-bestaande (999999, -1, ...) — geen foreign-key-afdwinging op die kolom, dus geen fout,
gewoon een stil "geslaagd" record. **Oplossing:** elke schrijffunctie valideert de userid
eerst tegen de betrouwbare `helper::get_allowed_students()`-lijst, vóór er iets geschreven
wordt.

### 8.5 `completionexpected` alleen aanpassen toont niets in de Tijdlijn

Het rechtstreeks wijzigen van `course_modules.completionexpected` past enkel het ruwe veld
aan. De kalender-actiegebeurtenis die het Tijdlijn-blok/Dashboard effectief leest, is een
**apart record**, aangemaakt/bijgewerkt via `\core_completion\api::update_completion_date_event()`
— exact de aanroep die Moodle's eigen bewerkingsformulier na het opslaan doet.
`set_completion_settings` roept dit nu ook expliciet aan.

### 8.6 `MESSAGE_DEFAULT_LOGGEDIN`/`MESSAGE_DEFAULT_LOGGEDOFF` zijn verwijderd

Oudere Moodle-documentatie en pluginvoorbeelden gebruiken deze twee constanten in
`db/messages.php`. In recente Moodle-versies (rond 4.5+) zijn ze **volledig verwijderd**
(niet enkel verouderd) — gebruik in plaats daarvan de enkele vlag `MESSAGE_DEFAULT_ENABLED`.
Een poging om de oude constanten te gebruiken geeft een harde `Undefined constant`-crash bij
de plugin-upgrade.

### 8.7 `core_calendar_create_calendar_events` ondersteunt geen persoonlijke gebeurtenis voor een andere gebruiker

Getest en bevestigd: het `eventtype: 'user'` accepteert geen `userid`-parameter voor een
andere gebruiker dan de aanroeper zelf (`invalidparameter`-fout: "Unexpected keys (userid)").
Dit sluit aan bij een eerdere, gerichte beveiligingsfix (MSA-22-0002) die `moodle/calendar:
manageentries` al beperkte tot Site/Categorie/Cursus-context, niet Gebruikerscontext. Er
bestaat dus geen ondersteunde manier om via webservice een gebeurtenis in de persoonlijke
kalender van een specifieke, andere leerling te plaatsen — vandaar de keuze voor
systeemnotificaties (hoofdstuk 7) in plaats van kalendergebeurtenissen voor dit doel.

### 8.8 `core_message_send_instant_messages` wordt geblokkeerd door contactprivacy

Een gewoon persoonlijk bericht (in tegenstelling tot een systeemnotificatie) wordt geweigerd
als de ontvanger zijn privacy-instelling op "enkel contacten" heeft staan en de afzender geen
contact is. **Oplossing:** een systeemnotificatie (`notification = 1` op een
`\core\message\message`-object, via een eigen geregistreerde berichtprovider) omzeilt deze
check, net als elke kern-Moodle-notificatie.

### 8.9 `core/modal_factory` is volledig verwijderd in Moodle 5.2

Niet gerelateerd aan deze plugin, maar relevant voor deze omgeving: de "Tiles" (Tegel)
cursusformaat-plugin (versie 5.1.0.2) roept nog `core/modal_factory` aan, wat in Moodle 5.2
volledig verwijderd is (niet enkel verouderd). Dit veroorzaakte een JavaScript-crash die
willekeurige, ongerelateerde front-end-functionaliteit (o.a. het meldingen-belletje) kon
blokkeren. **Workaround:** de "Modal activiteiten"/"Modal resources"-instellingen van de
Tiles-plugin uitschakelen tot er een 5.2-compatibele release is.

## 9. Gekende beperkingen

- **Geen persoonlijke kalendergebeurtenissen** voor individuele leerlingen mogelijk (zie 8.7)
  — systeemnotificaties zijn het ondersteunde alternatief.
- **`get_available_users`/`get_completion_status_bulk` zijn traag bij zeer grote cursussen**
  (honderden leerlingen), door het per-gebruiker berekeningsmodel (zie 8.3). Voor normale
  klasgroottes ruimschoots snel genoeg.
- **Write-service-account moet ingeschreven zijn in elke cursus** waar
  `override_completion_status(_bulk)` gebruikt wordt (zie 8.2) — vereist het opzetten van de
  dedicated-sitegroep-aanpak per nieuwe cursus.
- **Interne Moodle-API's, geen gegarandeerd stabiel extern contract.** `get_fast_modinfo()`,
  `\core_availability\info_module`, `\core_completion\api` zijn interne PHP-API's, geen
  officiële webservice-contracten. Test na elke grote Moodle-versie-upgrade opnieuw (zie
  hoofdstuk 10).

## 10. Testen

Gebruik `test_lti_deelnemers.js` (apart script, zie eigen documentatie in dat bestand) na elke
Moodle-upgrade als regressietest: cursus/LTI-activiteit opzoeken, deelnemerslijst + cohorten
tonen, ruwe respons desgewenst inspecteren (`:raw`-achtervoegsel).
