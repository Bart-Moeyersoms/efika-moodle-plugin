# Kandidaat-fix: sectie-zichtbaarheidscontrole (NIET geïnstalleerd)

**Status: opzijgezet, niet in productie.** Bewaard als `local_coursecohortinfo-v2026080404-sectiecheck-CANDIDATE.zip`.
Toekomstige ontwikkeling vertrekt van versie **2026080403** (zonder deze wijziging), niet van deze kandidaat.

## Wat deze versie zou toevoegen

`helper::get_allowed_students()` en de losstaande logica in `get_available_users` zouden, naast
`$usercminfo->uservisible` (zichtbaarheid van de **activiteit zelf**), ook expliciet
`$sectioninfo->uservisible` (zichtbaarheid van de **sectie** waarin de activiteit zit)
controleren. `get_available_users` zou bovendien groepen/cohorten van zowel de module- als de
sectiebeperking tonen, niet enkel die van de module.

## Aanleiding

Tijdens het testen leek een sectiebrede cohort-beperking (twee cohorten via een OF-voorwaarde
op sectieniveau) genegeerd te worden: `get_available_users` gaf voor cmid 456 `totalvisible:
29` (= alle ingeschreven leerlingen) terug, ondanks een bevestigde, actieve beperking op de
**sectie** (id 42) van die activiteit.

## Waarom deze fix uiteindelijk niet als noodzakelijk werd bevestigd

Nader onderzoek (rechtstreeks bij de gebruiker nagevraagd) toonde dat de samenstelling van de
twee betrokken cohorten toevallig **exact de volledige klas** dekte (14 + 14 leerlingen + 1
expliciete leerling = 29, gelijk aan het totaal aantal ingeschreven leerlingen). Het getal "29
van 29 zichtbaar" was dus een **correcte uitkomst** van de OF-voorwaarde, geen teken dat de
sectiebeperking genegeerd werd. De vermoede bug is oorspronkelijk afgeleid uit een toevallige
numerieke samenloop (zichtbaar-aantal = totaal-aantal), zonder eerst de effectieve
cohortsamenstelling te controleren — dat was een fout in de diagnose, niet noodzakelijk in de
oorspronkelijke code.

**Conclusie: er is (nog) geen bevestigd, reproduceerbaar geval gevonden waarbij de
oorspronkelijke code (zonder sectiecontrole) een fout resultaat gaf.**

## Wanneer deze kandidaat-fix wél opnieuw te overwegen

Als er ooit een concreet geval opduikt waarbij:
- een sectie een eigen "Toegang beperken"-voorwaarde heeft die **niet** toevallig samenvalt met
  het totaal aantal ingeschreven leerlingen, én
- `get_available_users`/`get_completion_status_bulk`/`override_completion_status_bulk`/
  `send_deadline_notification` daarbij aantoonbaar leerlingen als "toegelaten" behandelen die de
  activiteit in de praktijk (bevestigd via de Moodle-UI, "Aanmelden als") niet te zien krijgen —

dan is deze kandidaat-fix het uitgangspunt om opnieuw te bekijken. Let op: als er ondertussen
al andere wijzigingen op versie 2026080403 zijn doorgevoerd, moet de fix (zie hieronder)
opnieuw toegepast worden op de dan actuele bestanden, niet blindelings de bewaarde
kandidaat-zip herinstalleren.

## De wijziging zelf, voor later hergebruik

**`classes/local/helper.php`**, in `get_allowed_students()`:
```php
// Vóór:
if ($usercminfo->uservisible) {
    $allowed[$userid] = $user;
}

// Kandidaat-wijziging:
$sectioninfo = $usercminfo->get_section_info();
if ($usercminfo->uservisible && $sectioninfo->uservisible) {
    $allowed[$userid] = $user;
}
```

**`classes/external/get_available_users.php`**: dezelfde toevoeging in de eigen, losstaande
lus (deze functie gebruikt de gedeelde helper niet voor dit specifieke deel), plus het
samenvoegen van groups/cohorts uit zowel `$cminfo->availability` als
`$cminfo->get_section_info()->availability` (via een nieuwe `merge_unique_by_id()`-hulpfunctie).

## Praktisch effect als de fix ooit alsnog toegepast wordt

Geen regressie te verwachten: in elk geval waar module- en sectiezichtbaarheid al overeenkomen
(zoals in het geteste geval), verandert de uitkomst niet. De wijziging verstrengt de controle
enkel in het geval dat een sectiebeperking **afwijkt** van wat de module-eigen beperking alleen
zou opleveren.
