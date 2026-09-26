# Documentatie: Moodle ↔ Microsoft Entra ID koppeling & webservice-opzet

**School:** Dalton Gent / Lyceum Gent (efika.eu)
**Moodle-versie:** 5.2.1
**Laatst bijgewerkt:** 30 juli 2026

---

## Inhoud

1. [Overzicht van de architectuur](#1-overzicht-van-de-architectuur)
2. [local_o365 configuratie](#2-local_o365-configuratie)
3. [Patch: Object ID tonen in Manage Cohort sync](#3-patch-object-id-tonen-in-manage-cohort-sync)
4. [Cohort sync (klasgroepen)](#4-cohort-sync-klasgroepen)
5. [Gebruikersbeheer via User Sync](#5-gebruikersbeheer-via-user-sync)
6. [Cursusinschrijving en cursus-Groepen](#6-cursusinschrijving-en-cursus-groepen)
7. [Toegang beperken (availability)](#7-toegang-beperken-availability)
8. [Webservice-opzet voor externe toepassingen](#8-webservice-opzet-voor-externe-toepassingen)
9. [Eigen plugin: local_coursecohortinfo](#9-eigen-plugin-local_coursecohortinfo)
10. [Testscript: test_lti_deelnemers.js](#10-testscript-test_lti_deelnemersjs)
11. [Gekende quirks en aandachtspunten](#11-gekende-quirks-en-aandachtspunten)
12. [Handleiding: bestanden uitpakken en plaatsen](#12-handleiding-bestanden-uitpakken-en-plaatsen)
13. [Openstaande actiepunten](#13-openstaande-actiepunten)

---

## 1. Overzicht van de architectuur

```
Smartschool (klasindeling)
        │  dagelijkse sync
        ▼
Microsoft Entra ID
   ├─ Security groups per klas (bv. "3A", "3MW", ...)
   ├─ "Personeel" (alle personeelsleden)
   ├─ "Leerling security groep" (alle leerlingen)
   └─ "Moodle-toegang" (overkoepelende groep: bevat "Personeel" +
                          "Leerling security groep" als geneste leden)
        │
        ▼
local_o365 (Moodle-plugin)
   ├─ User Sync    → maakt/onderhoudt Moodle-accounts, beperkt tot leden
   │                  van "Moodle-toegang" (User Creation Restriction)
   └─ Cohort Sync  → houdt Moodle-sitegroepen (cohorten) gelijk met de
                      klas-security-groepen in Entra
        │
        ▼
Moodle Sitegroepen (Cohorten) - op Systeemniveau
   ├─ 3MW  (cohort-ID 4)
   ├─ 4BO  (cohort-ID 5)
   ├─ 3BO  (cohort-ID 3)
   └─ ...
        │  Sitegroepsynchronisatie (inschrijvingsmethode per cursus)
        ▼
Cursus-inschrijvingen + optioneel cursus-Groepen
        │
        ▼
Eigen webservice-laag (local_coursecohortinfo + kern-Moodle-functies)
        │
        ▼
Externe toepassing (classroom-validator / LTI-tool)
```

---

## 2. local_o365 configuratie

### 2.1 Tenant-instelling (kritieke fix)

**Probleem:** het veld "Microsoft Entra ID-tenant" (Setup > Stap 2/3) stond op `entra.microsoft.com` — de URL van het Microsoft-beheerportaal, geen geldige tenant-identifier. Dit veroorzaakte de fout *"Could not get app or system token"* / *"Cannot retrieve a token for the base resource"* bij elke functie die een app-only Graph-token nodig heeft (Cohort sync, User sync, ...).

**Oplossing:** vervangen door de effectieve tenant-GUID:
```
88680440-55c8-4588-b878-12c833038ee6
```
(Dezelfde GUID die ook correct werkt in de `auth_oidc`-configuratie voor de gewone login.)

**Belangrijk:** gebruik nooit de "Detecteer"-knop om dit op te lossen — die knop vereist zelf al een werkend app-token, en faalt dus net zo lang de tenant fout staat. Typ de GUID rechtstreeks in het tekstveld.

### 2.2 User Creation Restriction

**Locatie:** Sitebeheer > Plugins > Local plugins > Microsoft 365 Integration > Sync Settings > "User Creation Restriction"

**Instelling:** `Microsoft 365 Group Membership (group object ID)`, gekoppeld aan het Object ID van de groep **"Moodle-toegang"** in Entra.

**Waarom een overkoepelende groep in plaats van twee losse ID's:** het is niet met zekerheid bevestigd dat dit veld een kommagescheiden lijst van meerdere ID's correct verwerkt. Een enkele, overkoepelende security-groep die "Personeel" en "Leerling security groep" als **geneste leden** bevat, is de robuustere oplossing — de plugin gebruikt transitief (genest) groepslidmaatschap bij haar Graph-bevragingen.

**Effect:** enkel gebruikers die lid zijn van "Moodle-toegang" (rechtstreeks of via een geneste groep) worden nog aangemaakt bij een sync-run. Dit sluit service-/toepassingsaccounts en andere niet-relevante Entra-objecten uit.

**Belangrijk:** dit beïnvloedt enkel de **aanmaak** van nieuwe accounts, niet het schorsen van bestaande. Reeds bestaande, ongewenste accounts moeten apart opgekuist worden (zie 2.3).

### 2.3 Eenmalige opkuis van foutief aangemaakte accounts

Bij de eerste bulk user-sync (1335 accounts) kwamen ~54 ongewenste accounts mee (toepassings-/service-mailboxen zonder groepslidmaatschap). Deze zijn geïdentificeerd via een Excel-vergelijking (COUNTIF, kleine letters gelijkgemaakt tussen Moodle- en Entra-export) en manueel verwijderd via Bulkacties voor gebruikers.

### 2.4 Account-schorsing bij wijziging in Entra

local_o365's User Sync-taak schorst een Moodle-account automatisch wanneer het gekoppelde Entra-account gedeactiveerd/verwijderd wordt, en heft de schorsing weer op zodra het account terug actief blijkt. Dit is **gedocumenteerd, bewust gedrag**, geen bug. Een testaccount ("Flows Test") dat wisselend geschorst bleek, kwam uiteindelijk niet door dit mechanisme, maar door een verkeerd ingestelde **authenticatiemethode** (stond op OAuth i.p.v. OpenID Connect) — eenmaal gecorrigeerd, functioneerde het account weer normaal.

---

## 3. Patch: Object ID tonen in Manage Cohort sync

### 3.1 Waarom deze patch nodig is

Het zoekveld "Microsoft Group" op de "Manage Cohort sync"-pagina (zie §4.1) toont enkel de groepsnaam — geen Object ID, geen groepstype. Zoals beschreven in §4.2 leidt dit ertoe dat Teams en beveiligingsgroepen met dezelfde naam niet van elkaar te onderscheiden zijn in de UI, met als risico een foutieve koppeling. Deze patch toont het Object ID naast elke naam in de zoekresultaten, zodat je dat rechtstreeks kan vergelijken met wat je vooraf in Entra hebt opgezocht (Groepstype = Beveiliging).

**Dit is een wijziging aan een kernbestand van de plugin `local_o365` zelf — niet aan onze eigen `local_coursecohortinfo`-plugin.** Ze wordt daarom bij elke update van `local_o365` overschreven en moet dan opnieuw toegepast worden (zie §3.4).

### 3.2 Bestand

```
/var/www/efika-prod/public/local/o365/classes/webservices/cohortsync_search_groups.php
```

### 3.3 De wijziging

Zoek de functie `search_groups()`, helemaal onderaan:

**Voor (origineel):**
```php
$results = array_map(function ($r) {
    return ['id' => $r->id, 'displayName' => $r->displayname];
}, $page);
```

**Na (aangepast):**
```php
$results = array_map(function ($r) {
    return ['id' => $r->id, 'displayName' => $r->displayname . ' — ' . $r->id];
}, $page);
```

**Belangrijk:** enkel `displayName` aanpassen, **niet** `id`. Het veld `id` wordt door de JavaScript van de pagina gebruikt om de eigenlijke koppeling door te sturen bij selectie — dat moet het zuivere GUID blijven.

Na deze wijziging toont de zoeklijst bijvoorbeeld:
```
3A — 37896ce1-c4d8-4426-add0-659950845f8d
3A — cc13866c-aa00-48b3-918d-94a57f381dcc
```

### 3.4 Toepassen (en na elke local_o365-update herhalen)

```bash
# Back-up van het originele bestand
sudo -u www-data cp /var/www/efika-prod/public/local/o365/classes/webservices/cohortsync_search_groups.php \
                     /var/www/efika-prod/public/local/o365/classes/webservices/cohortsync_search_groups.php.bak

# Bestand bewerken (nano/vim) en de regel hierboven aanpassen
```

Rechten controleren na het bewerken (zou ongewijzigd moeten blijven bij in-place bewerken, maar ter controle):
```bash
ls -l /var/www/efika-prod/public/local/o365/classes/webservices/cohortsync_search_groups.php
```
Vergelijk eigenaar/rechten met een buurbestand in dezelfde map; zo nodig herstellen:
```bash
sudo chown www-data:www-data /var/www/efika-prod/public/local/o365/classes/webservices/cohortsync_search_groups.php
sudo chmod 644 /var/www/efika-prod/public/local/o365/classes/webservices/cohortsync_search_groups.php
```

Cache legen (PHP OPcache kan de oude versie blijven tonen):
```bash
sudo systemctl restart php8.3-fpm   # versienummer aanpassen indien nodig
```
Of via Moodle: Sitebeheer > Ontwikkeling > Caches beheren > Alle caches wissen.

**Testen:** ga naar Manage Cohort sync, zoek een klasnaam op, en controleer of het Object ID nu achter elke naam verschijnt.

### 3.5 Procedure na een local_o365-update

1. Check na elke update van `local_o365` (via de Moodle-pluginbeheerder of handmatig) of het probleem terug is: zoekresultaten tonen dan enkel de naam, geen GUID meer.
2. Zoek de functie `search_groups()` opnieuw op in het bijgewerkte bestand — vergelijk eerst met §3.3 vooraleer de patch blindelings opnieuw toe te passen, aangezien de exacte inhoud/structuur tussen versies van de plugin kan wijzigen.
3. Pas dezelfde wijziging opnieuw toe (stappen uit §3.4).

**Idee voor later:** dit lijkt een breder gevoeld gebrek in de plugin (zie ook de gerelateerde, publieke GitHub-issue over de 30-resultaten-limiet in datzelfde zoekscherm, §11). Overweeg deze verbetering als pull request voor te stellen bij `microsoft/moodle-local_o365`, zodat ze uiteindelijk in de officiële plugin terechtkomt en dit niet telkens manueel hertoegepast moet worden.

---

## 4. Cohort sync (klasgroepen)

### 4.1 De koppeling zelf

**Locatie:** Sitebeheer > Plugins > Local plugins > Microsoft 365 Integration > Sync Settings > "Manage Cohort sync"

Elke Moodle-sitegroep (op **Systeemniveau**, aangemaakt via Sitebeheer > Gebruikers > Sitegroepen) wordt hier gekoppeld aan het Object ID van de overeenkomstige Entra-**beveiligingsgroep**.

### 4.2 Belangrijke valkuil: Teams vs. Beveiligingsgroepen

Wanneer de (ondertussen uitgeschakelde) **Cursussynchronisatie/Course Sync**-functie ooit actief was, maakte ze voor cursussen automatisch **Microsoft 365-Teams** aan met dezelfde naam als de bijhorende klas-beveiligingsgroep. Bij het koppelen in "Manage Cohort sync" toont het zoekveld **beide** groepstypes door elkaar, zonder onderscheid — met als risico dat je per ongeluk de Team-groep koppelt in plaats van de echte beveiligingsgroep.

**Gebeurd op 24 juli 2026:** cohorten 3 en 4 waren gekoppeld aan Team-groepen (met leden uit een oude testfase) in plaats van de juiste beveiligingsgroepen. Hersteld door in Entra het **Groepstype** (Beveiliging vs. Microsoft 365) te controleren en de juiste Object ID's opnieuw te koppelen.

**Werkwijze om dit te vermijden:**
1. Zoek de gewenste groep in Entra op en controleer het Groepstype = **Beveiliging**.
2. Noteer het Object ID.
3. Als er dubbele namen in het zoekveld van Moodle verschijnen: hernoem de beveiligingsgroep tijdelijk uniek in Entra (bv. `3A-MOODLE-TEMP`), koppel op die unieke naam, hernoem daarna terug. De koppeling zelf blijft op Object ID gebaseerd en overleeft de hernoeming.
4. **Verifieer altijd** na het koppelen: voer de cohortsync-taak handmatig uit via CLI en vergelijk de resulterende ledenlijst met de effectieve Entra-ledenlijst.

### 4.3 Huidige koppelingen (stand van zaken)

| Cohort-ID | Naam | Entra Object ID | Status |
|---|---|---|---|
| 3 | 3BO | `9ebb0aaa-9d54-41b1-bee7-e2bf29afe317` | Correct (beveiligingsgroep) |
| 4 | 3MW | `bc5dc602-9d01-4f3c-b2bb-e081751d35a7` | Correct (beveiligingsgroep) |
| 5 | 4BO | `2e40cd08-2d86-41c2-a5af-b0ba319c6d75` | Correct |
| 6 | (nog te bevestigen) | | Correct volgens eerste log-analyse |

### 4.4 CLI-commando om de sync manueel te forceren

```bash
sudo -u www-data php /var/www/efika-prod/public/admin/cli/scheduled_task.php --execute='\local_o365\task\cohortsync'
```

### 4.5 Object ID als idnumber bewaren

Elke sitegroep krijgt bij aanmaak best het Entra Object ID als `idnumber` (niet enkel als naam), zodat de koppeling ondubbelzinnig na te trekken blijft, ook als een klasnaam ooit hernoemd of hergebruikt wordt.

---

## 5. Gebruikersbeheer via User Sync

### 5.1 CLI-commando

```bash
sudo -u www-data php /var/www/efika-prod/public/admin/cli/scheduled_task.php --execute='\local_o365\task\usersync'
```

### 5.2 Wat dit doet

- Maakt Moodle-accounts aan voor Entra-gebruikers die nog geen account hebben (mits ze aan de User Creation Restriction voldoen).
- Koppelt (matcht) bestaande Moodle-accounts aan hun Microsoft-identiteit op basis van UPN/e-mailadres.
- Werkt profielvelden bij (voornaam, achternaam, e-mail, taal, idnumber, afdeling, telefoon) volgens de geconfigureerde field mappings.
- Schorst/deschorst accounts naargelang hun status in Entra (zie 2.4).

### 5.3 Effect op sitegroepen

Zodra een account via User Sync gekoppeld ("Linked to Moodle account #...") is aan zijn Microsoft-identiteit, kan de eerstvolgende Cohort sync-run dat account ook effectief aan de juiste sitegroep toevoegen. Een account dat wel bestaat maar nooit gekoppeld werd (bv. via een oudere, niet-Microsoft-gerelateerde aanmaakwijze), wordt door Cohort sync genegeerd.

---

## 6. Cursusinschrijving en cursus-Groepen

### 6.1 Sitegroepsynchronisatie (inschrijvingsmethode)

Per cursus toegevoegd via Deelnemers > Inschrijvingsmethodes > "Sitegroepsynchronisatie", gekoppeld aan de gewenste sitegroep/cohort. Optie **"Groep toevoegen"**: maakt automatisch een echte cursus-Groep aan met dezelfde naam, en houdt de leden ervan live gesynchroniseerd.

**Aandachtspunt:** een eenmalige, manuele "Cohort inschrijven"-actie (via Deelnemers > Gebruikers inschrijven) creëert **geen** cursus-Groep en houdt niets automatisch bij — voor doorlopende synchronisatie is de inschrijvingsmethode vereist, niet de eenmalige bulkactie.

### 6.2 Groep versus Groepering

- **Groep:** basiseenheid, individuele leerlingen (bv. "3A").
- **Groepering:** bundel van meerdere Groepen (bv. "Derde jaar" = 3A + 3B + 3C).

### 6.3 Groepswerk (duo's, ...)

Cohort-lidmaatschap regelt **nooit** groepswerk-indeling. Voor "Studenten dienen in groepen in" (gedeelde inzending) binnen een Opdracht is een echte cursus-Groep vereist, los van de klas-cohorten. Aanbeveling: aparte Groeperingen per duo-indeling, onafhankelijk van de klas-Groepen.

**Let op:** "Studenten dienen in groepen in" en de gekoppelde Groepering worden **vergrendeld** zodra één leerling al iets heeft ingediend.

### 6.4 availability_cohort (extra plugin)

Voegt een "Cohort"-voorwaarde toe aan "Toegang beperken", zodat content rechtstreeks op basis van cohort-lidmaatschap afgeschermd kan worden, **zonder** dat daarvoor een cursus-Groep nodig is. Nuttig voor cursussen met meerdere klassen waar je enkel toegang wil beperken, niet daadwerkelijk groepswerk wil organiseren.

**Belangrijke beperking (bevestigd):** deze plugin implementeert **niet** `is_applied_to_user_lists()`/`filter_user_list()`. Cohort-voorwaarden worden daardoor door Moodle's bulk-filterfunctie genegeerd (iedereen zou als "zichtbaar" getoond worden) — vandaar de aanpak in `local_coursecohortinfo` om per gebruiker apart te controleren (zie 9.2).

---

## 7. Toegang beperken (availability)

### 7.1 Opgeslagen als JSON

In `mdl51_course_modules.availability`, structuur:
```json
{"op": "&", "c": [ ... voorwaarden ... ], "showc": [true, false, ...]}
```
- `op`: `&` (allemaal), `|` (minstens één), `!&`, `!|`
- `c`: voorwaarden of geneste sub-boomstructuren
- `showc`: per voorwaarde, oog open (`true`, grijs tonen) of doorgestreept (`false`, volledig verbergen)

### 7.2 Kernvoorwaarde-types

| `type` | Velden | Betekenis |
|---|---|---|
| `group` | `id` | Groep |
| `grouping` | `id` | Groepering |
| `cohort` | `id` | Cohort (vereist availability_cohort) |
| `date` | `d`, `t` | Datum |
| `completion` | `cm`, `e` | Activiteitsvoltooiing |
| `grade` | `id`, `min`, `max` | Behaald cijfer |
| `profile` | `sf`/`cf`, `op`, `v` | Profielveld (standaard resp. aangepast) |

Nieuwe plugins kunnen extra types toevoegen — geen gesloten lijst. Check Sitebeheer > Plugins > Availability restrictions > Manage restrictions voor het actuele overzicht.

### 7.3 Waarom eigen JSON-parsing onbetrouwbaar bleek

Een eerste implementatie (JavaScript, in de eigen tool) parste de ruwe JSON zelf, maar bleek fouten te vertonen bij bepaalde profiel-/e-mailvoorwaarden. **Vervangen door een server-side aanpak** die Moodle's eigen beslissingslogica hergebruikt (zie 9.2) — dat is de aanbevolen, betrouwbare methode, in plaats van de JSON zelf te blijven interpreteren.

---

## 8. Webservice-opzet voor externe toepassingen

### 8.1 Architectuurprincipes

- **Protocol:** REST (JSON), geen SOAP/XML-RPC.
- **Least privilege:** een dedicated, niet-persoonlijk account, met een eigen, minimale rol (archetype "Geen"), enkel de strikt noodzakelijke capabilities.
- **Toegelaten-gebruikerslijst:** de service staat op "Vereist geautoriseerde gebruikers" — enkel het dedicated account mag er een token voor krijgen.
- **Schrijfrechten strikt gescheiden van leesrechten:** voor kalenderaanmaak (`core_calendar_create_calendar_events`) wordt een aparte rol/service aanbevolen, niet toegevoegd aan het lezende account.

### 8.2 Account & rol

- **Gebruiker:** "webservice MoodleClassroom" (mil@moeyersoms.be)
- **Rol:** `MoodleClassroom_Rest`, archetype **Geen**, toewijsbaar op **Systeem**-niveau
- **Service:** `moodleclassroomES`

### 8.3 Opgebouwde capability-lijst (leesrol)

| Capability | Reden |
|---|---|
| `webservice/rest:use` | Basisvereiste voor REST-webservices |
| `moodle/course:view` | Cursussen bekijken zonder inschrijving |
| `moodle/course:viewparticipants` | Deelnemerslijst bekijken |
| `moodle/course:useremail` | E-mailadres tonen in deelnemerslijst |
| `moodle/user:viewdetails` | Volledig gebruikersprofiel bekijken (rollen, cursussen) |
| `moodle/site:viewuseridentity` | Identiteitsvelden (o.a. e-mail) van andere gebruikers tonen |
| `moodle/user:viewhiddendetails` | Persoonlijk verborgen e-mailadressen alsnog zien |
| `moodle/course:managegroups` | Vereist door `core_group_get_course_groups`/`get_groups` (leesfuncties, ondanks de naam) |
| `moodle/site:accessallgroups` | Deelnemers/groepen zien ongeacht groepsmodus (Gescheiden/Zichtbare groepen) |
| `moodle/course:viewhiddenactivities` | Verborgen/beperkte activiteiten zien |
| `mod/lti:view` | Specifiek vereist om LTI/Externe tool-activiteiten te mogen bekijken |
| `report/progress:view` | Vereist door `core_completion_get_activities_completion_status` (**niet** `report/completion:view`, ondanks de gelijkaardige naam) |
| `moodle/cohort:view` | Vereist door `core_cohort_get_cohort_members` |
| `local/coursecohortinfo:view` | Eigen capability voor de eigen plugin-functies (zie hoofdstuk 9) |

**Apart, voor schrijven (aanbevolen aparte rol/service):**
| Capability | Reden |
|---|---|
| `moodle/calendar:manageentries` | Vereist door `core_calendar_create_calendar_events` (kan ook bestaande kalendergegevens bewerken/verwijderen — brede capability, vandaar de aanbevolen scheiding) |

### 8.4 Functielijst in gebruik

**Kern-Moodle-functies:**
- `core_course_get_contents`
- `core_course_get_course_module`
- `core_enrol_get_enrolled_users`
- `core_group_get_course_groups`
- `core_cohort_get_cohort_members`
- `core_completion_get_activities_completion_status`
- `core_calendar_get_calendar_events`

**Eigen functies (zie hoofdstuk 9):**
- `local_coursecohortinfo_get_course_cohort_enrolments`
- `local_coursecohortinfo_get_available_users`
- `local_coursecohortinfo_get_courses_using_lti_type`

### 8.5 Bekende, niet-gebruikte/afgeraden functies

- `core_enrol_get_course_enrolment_methods`: geeft enkel methodes terug die de gebruiker **zelf** kan initiëren (zelfinschrijving, gastentoegang) — toont Sitegroepsynchronisatie/handmatige inschrijving structureel **niet**, ook niet als ze actief zijn. Niet geschikt om te bepalen welk cohort aan een cursus gekoppeld is.

---

## 9. Eigen plugin: local_coursecohortinfo

**Pad op server:** `/var/www/efika-prod/public/local/coursecohortinfo/`
**Huidige versie:** 2026073000

### 9.1 Functie: `get_course_cohort_enrolments`

Geeft, voor een `courseid`, alle cohort-gebaseerde inschrijvingsmethodes terug: enrolment-ID, cohort-ID, cohortnaam, cohort-idnumber (Entra GUID), gekoppelde cursusgroep-ID, rol, status. Rechtstreekse, geparametriseerde SQL-query op `{enrol}` + `{cohort}`.

### 9.2 Functie: `get_available_users`

Geeft, voor een `cmid`, de effectieve lijst ingeschreven **leerlingen** (leerkrachten/niet-bewerkende leerkrachten expliciet uitgesloten op basis van rol-archetype) terug die de activiteit mogen zien, plus de betrokken groepen/cohorten (met naam).

**Kernmethode:** per ingeschreven leerling apart `get_fast_modinfo($course, $userid)` + `->uservisible` — **niet** de snellere bulkmethode `\core_availability\info_module::filter_user_list()`, omdat die enkel voorwaarden toepast waarvoor de betrokken availability-plugin `is_applied_to_user_lists()` expliciet implementeert (bevestigd: `availability_cohort` doet dit niet). De per-gebruiker aanpak is trager bij grote cursussen, maar **altijd correct**, ongeacht voorwaarde-type of EN/OF/NIET-combinatie.

**Contextvalidatie:** bewust tegen de **cursuscontext**, niet de **modulecontext** — validatie tegen de modulecontext zou zelf een `require_login`-achtige controle triggeren die vereist dat het *aanroepende* account aan de beperking van de activiteit voldoet, wat net omzeild moet worden voor een rapporterend service-account.

### 9.3 Functie: `get_courses_using_lti_type`

Geeft, voor een `ltitypeid` (uit `mdl_lti_types`), de lijst cursussen terug die minstens één activiteit van dat specifieke, geregistreerde LTI-tooltype bevatten. Voorkomt dat een client-tool alle cursussen op de site moet doorzoeken.

### 9.4 Eigen capability

`local/coursecohortinfo:view` — bewust nieuw en apart gedefinieerd (niet hergebruikt van een bestaande, bredere capability), standaard aan niemand toegekend, contextlevel CONTEXT_COURSE.

### 9.5 Versiehistoriek (samengevat)

| Versie | Wijziging |
|---|---|
| 2026072900 | Eerste versie: `get_course_cohort_enrolments` |
| 2026072901 | `get_available_users` toegevoegd (eerste versie, bulk `filter_user_list`) |
| 2026072902 | Groepen/cohorten (met naam) toegevoegd aan `get_available_users`-respons |
| 2026072903 | Contextvalidatie gecorrigeerd (cursus- i.p.v. modulecontext) |
| 2026072904 | Bulkfiltering vervangen door per-gebruiker `uservisible`-controle (cohort-plugin-beperking) |
| 2026072905 | Leerkrachten/niet-bewerkende leerkrachten uitgefilterd uit de leerlingenlijst |
| 2026073000 | `get_courses_using_lti_type` toegevoegd |

---

## 10. Testscript: test_lti_deelnemers.js

**Doel:** regressietest na elke Moodle-upgrade, en praktisch opzoekingshulpmiddel.

**Locatie:** lokaal bij Bart (bv. `C:\Users\bart\downloads\test_lti_deelnemers.js`), Node.js 18+.

**Werking:**
1. Vraagt het webservice-token interactief op (onzichtbare invoer, nooit opgeslagen).
2. Toont enkel de cursussen die minstens één activiteit van het geconfigureerde LTI-tooltype bevatten (via `LTI_TOOL_TYPE_ID`, in te stellen bovenaan het script - zoek het effectieve ID op via `SELECT id, name FROM mdl51_lti_types;`).
3. Na het kiezen van een cursus: toont de LTI-activiteiten (cmid + naam) in die cursus.
4. Typ een cmid: toont Naam - E-mail - Cohort per leerling die de activiteit mag zien, plus welke groepen/cohorten aan de basis van de beperking liggen.
5. `:raw` achter een cmid (bv. `388:raw`): toont de ruwe JSON-respons.
6. `list`: activiteitenlijst van de huidige cursus opnieuw tonen.
7. `cursus`: een andere cursus kiezen.
8. `exit`: script afsluiten.

**Foutopsporing:** elke fout wordt beknopt op het scherm getoond én met volledige technische details (inclusief Moodle's debuginfo) weggeschreven naar `debug.log` in de map van waaruit het script gestart wordt. Het token wordt nooit gelogd.

**Configuratievariabelen (bovenaan het bestand):**
```javascript
const MOODLE_BASE_URL = 'https://efika.eu/webservice/rest/server.php';
const MODULE_TYPE_TO_FIND = 'lti';
const LTI_TOOL_TYPE_ID = 0; // in te vullen
const DEBUG_LOG_FILE = 'debug.log';
```

**Vereiste functies in de service:** zie de lijst in §8.4 (met uitzondering van `core_course_get_course_module`, dat voor dit script niet meer nodig is sinds de courseid rechtstreeks uit de cursuskeuze wordt hergebruikt in plaats van apart opgezocht).

**Gekende, opgeloste bug:** een eerdere versie zocht het courseid van een cmid op via de rauwe kernfunctie `core_course_get_course_module`, die hetzelfde "Activity is restricted"-probleem heeft als wat in `get_available_users` werd opgelost (zie §9.2). Deze aanroep is volledig verwijderd; het courseid wordt nu rechtstreeks doorgegeven vanuit de reeds gekozen cursus.

---

## 11. Gekende quirks en aandachtspunten

| Onderwerp | Aandachtspunt |
|---|---|
| "Could not check reply url" | Cosmetische, bekende bug in de Setup-verificatiepagina van local_o365. Genegeerd, geen functionele impact. |
| Zoekveld "Manage Cohort sync" | Toont maximaal 30 resultaten zonder duidelijke sortering/filter (bekende GitHub-issue). Zoek op exacte naam. |
| Duplicaten in het zoekveld | Toont Teams én beveiligingsgroepen door elkaar, zonder onderscheid. Controleer altijd het Groepstype rechtstreeks in Entra. |
| `enableavailability` | Site-brede instelling (Sitebeheer > Geavanceerde functies) die, indien uitgeschakeld, **alle** Toegang beperken-voorwaarden negeert. Stond bij ons reeds aan. |
| `get_activities_completion_status` vs. `get_course_completion_status` | Vereisen elk een andere, gelijkaardig klinkende capability (`report/progress:view` resp. `report/completion:view`) — niet verwisselen. |
| Cursusformat "Tegel" | Louter een weergavelaag; heeft geen invloed op de onderliggende sectie-/moduledata of op API-resultaten. |
| iCal-export naar Google Calendar | Enkel eenrichtings- en niet-realtime (Google ververst onvoorspelbaar traag). Geen ingebouwde, betrouwbare tweerichtingssync beschikbaar in Moodle. |

---

## 12. Handleiding: bestanden uitpakken en plaatsen

Deze stappen gelden voor elke update van de `local_coursecohortinfo`-plugin (of vergelijkbare plugin-bestanden), telkens uitgevoerd via SSH op de server.

### Stap 1 — Bestand uitpakken

```bash
cd ~
unzip local_coursecohortinfo.zip
```
**Resultaat:** een map `local_coursecohortinfo` in de huidige map, met daarin `classes/`, `db/`, `lang/`, `version.php`.

*(`unzip` niet beschikbaar? Installeer met `sudo apt install unzip` en herhaal.)*

### Stap 2 — Bestaande map opzijzetten (hernoemen als back-up)

```bash
sudo mv /var/www/efika-prod/public/local/coursecohortinfo /var/www/efika-prod/public/local/coursecohortinfo.bak
```
**Resultaat:** de oude versie blijft veilig bewaard onder een andere naam, voor het geval de nieuwe versie problemen geeft.

**Waarschuwing:** `mv nieuwe_map bestaande_map` plaatst de nieuwe map **in** de bestaande map (met behoud van haar eigen naam) als de doelmap al bestaat — vandaar dat de oude map hier eerst apart hernoemd wordt.

### Stap 3 — Nieuwe map op de juiste plek zetten

```bash
sudo mv ~/local_coursecohortinfo /var/www/efika-prod/public/local/coursecohortinfo
```
**Resultaat:** de nieuwe pluginversie staat nu op de effectieve locatie.

### Stap 4 — Rechten controleren en zo nodig herstellen

```bash
ls -la /var/www/efika-prod/public/local/coursecohortinfo/
```
**Verwacht resultaat:** eigenaar en groep `www-data www-data`, rechten `drwxr-xr-x`, identiek aan buurmappen zoals `o365`.

Indien afwijkend:
```bash
sudo chown -R www-data:www-data /var/www/efika-prod/public/local/coursecohortinfo
sudo find /var/www/efika-prod/public/local/coursecohortinfo -type d -exec chmod 755 {} \;
sudo find /var/www/efika-prod/public/local/coursecohortinfo -type f -exec chmod 644 {} \;
```

### Stap 5 — Inhoud controleren

```bash
ls -la /var/www/efika-prod/public/local/coursecohortinfo/classes/external/
```
**Verwacht resultaat:** alle drie de functiebestanden aanwezig, met een recent tijdstip:
- `get_course_cohort_enrolments.php`
- `get_available_users.php`
- `get_courses_using_lti_type.php`

### Stap 6 — Versienummer controleren

```bash
grep version /var/www/efika-prod/public/local/coursecohortinfo/version.php
```
**Verwacht resultaat:** `$plugin->version   = 2026073000;` (of de meest recente versie — zie §9.5 voor de volledige historiek).

### Stap 7 — Upgrade bevestigen in Moodle

Ga naar **Sitebeheer** (`https://efika.eu/admin/index.php`). Moodle detecteert het verhoogde versienummer automatisch en toont een upgradescherm ("non_core"-plugins, normaal gedrag voor elke zelfgeschreven plugin). Bevestig.

### Stap 8 — Functies koppelen aan de service (enkel bij een nieuwe functie)

Sitebeheer > Plugins > Webservices > Externe services > `moodleclassroomES` > voeg de nieuwe functienaam toe. Nodig telkens een nieuwe functie is toegevoegd — een versie-upgrade alleen volstaat niet om een gloednieuwe functie bruikbaar te maken.

### Stap 9 — Opruimen

Werkt alles naar wens:
```bash
sudo rm -rf /var/www/efika-prod/public/local/coursecohortinfo.bak
```

---

## 13. Openstaande actiepunten

- [ ] **Augustus-gesprek met Smartschool/IT-contact:**
  - Worden Entra-security groepen jaarlijks hergebruikt of opnieuw aangemaakt?
  - Bestaan er fijnmazige leerkracht-vakgroepen (per vak/klas), of enkel brede groepen zoals "Personeel"?
  - Opruiming van de legacy Team-groepen (ontstaan door de vroegere Cursussynchronisatie)?
  - Was het testaccount "Flows Test" ooit bewust gedeactiveerd/gereactiveerd in Entra rond 21 juli?
- [ ] Jaarlijkse-overgangsprocedure (augustus) effectief toepassen: Sitegroepsynchronisatie loskoppelen van oude cursussen vóór de nieuwe klasindeling actief wordt.
- [ ] `local_cohortrole`-plugin evalueren voor automatische toekenning van de rol **Cursusaanmaker** (niet Leerkracht) aan de "Personeel"-cohort — Moodle 5.2-compatibiliteit bevestigen vóór productie-inzet.
- [ ] `availability_cohort`-compatibiliteit met Moodle 5.2 bevestigen bij de pluginontwikkelaar/community (laatst bevestigde ondersteuning: 5.1).
- [ ] `LTI_TOOL_TYPE_ID` invullen in het testscript (zie §10) na opzoeking in `mdl_lti_types`.
- [ ] Periodiek (bv. na elke Moodle-upgrade) `test_lti_deelnemers.js` uitvoeren als regressietest.
- [ ] Overwegen: eigen script/policy voor het periodiek opschorten (niet verwijderen) van Moodle-accounts van leerlingen die de school verlaten en niet langer in Entra bestaan.
