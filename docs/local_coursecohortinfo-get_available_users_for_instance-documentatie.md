# Nieuwe functie: get_available_users_for_instance (versie 2026080405)

## Wat jij moet doen (Moodle-kant)

1. Plugin bijwerken naar versie **2026080405** (gewone procedure: map vervangen, rechten,
   Sitebeheer-upgrade).
2. Voeg **`local_coursecohortinfo_get_available_users_for_instance`** toe aan de **leesservice**
   (dezelfde service als `MOODLE_WS_TOKEN` al gebruikt) — geen nieuwe capability nodig,
   hergebruikt `local/coursecohortinfo:view`.

## Wat je terug moet geven aan de app

**Functienaam:** `local_coursecohortinfo_get_available_users_for_instance`

**Parameters:**
- `courseid` (int)
- `instance` (int) — het `mod_lti`-instance-ID, exact zoals gevraagd

**Belangrijke, niet-triviale aanname om door te geven aan het app-team:** deze functie zoekt
enkel binnen activiteiten van moduletype **`lti`**. Instance-ID's zijn namelijk enkel uniek
*binnen* de tabel van één moduletype — zonder deze beperking zou hetzelfde instance-nummer
toevallig naar een compleet andere activiteit kunnen verwijzen als er ergens een
niet-LTI-activiteit met hetzelfde instance-ID bestaat. Voor jullie huidige gebruik (enkel LTI)
is dit geen beperking, maar het is goed dat ze dit weten voor het geval dat ooit verandert.

**Response-vorm — exact zoals gevraagd, plus het opgezochte `cmid`:**
```json
{
  "cmid": 456,
  "totalenrolled": 62,
  "totalvisible": 18,
  "users": [{"id": 123, "fullname": "...", "email": "..."}],
  "groups": [],
  "cohorts": [{"id": 9, "name": "6ONOSa", "idnumber": "..."}, {"id": 10, "name": "...", "idnumber": "..."}]
}
```

**Foutgedrag:** als er geen `lti`-activiteit met dat instance-ID in die cursus bestaat, geeft de
functie een duidelijke `invalidrecordunknown`-fout terug (i.p.v. stil "geen beperking" te
suggereren) — de app kan dit gebruiken om een echte configuratiefout (verkeerd instance-ID) te
onderscheiden van "geen toegang".

## Wat ik heb gecorrigeerd t.o.v. de letterlijke specificatie, en waarom

De specificatie stelde voor om **`\core_availability\info_module::filter_user_list()`** te
gebruiken. Ik heb dit **niet** zo geïmplementeerd — dat zou een regressie zijn geweest.

**Bevestigd in Moodle's eigen broncode** (`availability/classes/info_module.php`):
`filter_user_list()` filtert uitsluitend op voorwaarden waarvoor de betrokken
availability-plugin `is_applied_to_user_lists() => true` implementeert. We hebben in dit
traject al eerder hard vastgesteld dat de cohort-plugin (`availability_cohort`) dit niet doet —
cohort-voorwaarden zouden door deze bulkmethode dus gewoon **genegeerd** worden, exact de
oorspronkelijke bug die `get_available_users` destijds al opgeloste door over te schakelen naar
een per-gebruiker aanpak.

**In plaats daarvan hergebruikt de nieuwe functie de bestaande, al beveiligde
`helper::get_allowed_students()`** — dezelfde per-gebruiker `get_fast_modinfo()`+`uservisible`-
berekening die `get_available_users` al gebruikt. Moodle's eigen ontwikkelaarsdocumentatie
bevestigt dat dit net de **aanbevolen** manier is om de toegang van één specifieke, gekende
gebruiker te controleren, en dat `uservisible` daarbij automatisch al de beperking van de
sectie combineert met die van de module zelf — precies het gedrag dat de specificatie zocht.

## Wat er structureel is opgekuist

De group/cohort-extractielogica (voorheen enkel intern in `get_available_users`) is verplaatst
naar de gedeelde `helper`-klasse (`extract_groups_and_cohorts()`), zodat beide functies dezelfde
code hergebruiken in plaats van een duplicaat te onderhouden.

## Over de eerder opzijgezette "sectiecheck"-kandidaat-fix

Dit lost het probleem op een andere manier op dan die kandidaat-fix voorstelde: niet door een
extra, eigen `$sectioninfo->uservisible`-controle toe te voegen aan de bestaande
`get_available_users(cmid)`, maar door een **nieuwe cmid-opzoekingsweg** te bieden die het
onderliggende "cmid onvindbaar bij sectiebeperking"-probleem vermijdt. De opzijgezette
kandidaat-fix blijft dus terecht opzijgezet — dit is een ander, gerichter probleem met een eigen
oplossing.
