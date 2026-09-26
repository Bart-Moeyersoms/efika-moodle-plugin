# Patch: Object ID tonen bij Cohort Sync (local_o365)

## Waarom deze patch nodig is

Bij het koppelen van een klasgroep in **Manage Cohort sync** (Site administration > Plugins >
Local plugins > Microsoft 365 Integration > Sync Settings > Manage Cohort sync) toont het
zoekveld "Microsoft Group" enkel de groepsnaam, zonder verder onderscheid.

Bij ons komt dezelfde naam tot 4 keer voor in de resultaten, omdat:
- er een **beveiligingsgroep** (Groepstype: Beveiliging) bestaat met de klasnaam (dit is de
  correcte groep, gesynchroniseerd vanuit Smartschool),
- er ook een of meerdere **Microsoft 365-Teams** bestaan met exact dezelfde naam (aangemaakt
  door de – ondertussen uitgeschakelde – Cursussynchronisatie/Course Sync-functie).

Zonder extra informatie is het onmogelijk om in de UI het verschil te zien, en moet je gokken.
Deze patch toont het **Object ID (GUID)** naast elke naam in de zoekresultaten, zodat je dit
kan vergelijken met het Object ID dat je vooraf zelf hebt opgezocht in Entra admin center
(waar je wél het Groepstype kan controleren).

## Bestand

```
/var/www/efika-prod/public/local/o365/classes/webservices/cohortsync_search_groups.php
```

## Wat aan te passen

Zoek de functie `search_groups()`, en helemaal onderaan deze regel:

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

**Belangrijk:** enkel `displayName` aanpassen, **niet** `id`. Het veld `id` wordt door de
JavaScript van de pagina gebruikt om de eigenlijke koppeling door te sturen bij selectie — dat
moet het zuivere GUID blijven.

## Stappenplan om toe te passen

1. **Back-up maken:**
   ```bash
   sudo -u www-data cp /var/www/efika-prod/public/local/o365/classes/webservices/cohortsync_search_groups.php \
                        /var/www/efika-prod/public/local/o365/classes/webservices/cohortsync_search_groups.php.bak
   ```

2. **Bestand bewerken** (bv. met `nano` of `vim`) en de regel hierboven aanpassen.

3. **Rechten controleren** — moet ongewijzigd blijven na bewerken in-place, maar ter controle:
   ```bash
   ls -l /var/www/efika-prod/public/local/o365/classes/webservices/cohortsync_search_groups.php
   ```
   Vergelijk eigenaar/rechten met een buurbestand in dezelfde map. Zet zo nodig terug:
   ```bash
   sudo chown www-data:www-data /var/www/efika-prod/public/local/o365/classes/webservices/cohortsync_search_groups.php
   sudo chmod 644 /var/www/efika-prod/public/local/o365/classes/webservices/cohortsync_search_groups.php
   ```

4. **Cache legen** (PHP OPcache kan de oude versie blijven tonen):
   ```bash
   sudo systemctl restart php8.3-fpm   # pas versienummer aan indien nodig
   ```
   Of via Moodle: **Sitebeheer > Ontwikkeling > Caches beheren > Alle caches wissen**.

5. **Testen:** ga naar Manage Cohort sync, zoek een klasnaam op, en controleer of het Object ID
   nu achter elke naam verschijnt (bv. `3A — 37896ce1-c4d8-4426-add0-659950845f8d`).

## Let op bij een volgende plugin-update

Deze wijziging zit in een **kernbestand van de plugin** (`local_o365`), niet in een eigen
override. Bij elke update van de plugin (via Moodle's pluginbeheerder of handmatig) wordt dit
bestand **overschreven** en verdwijnt de aanpassing.

Na een update:
1. Check of het probleem terug is (zoekresultaten tonen enkel de naam, geen GUID meer).
2. Zoek de functie `search_groups()` opnieuw op in het bijgewerkte bestand — de exacte
   inhoud/structuur kan licht wijzigen tussen versies, dus vergelijk eerst met dit document
   vooraleer je de patch blindelings opnieuw toepast.
3. Pas dezelfde wijziging opnieuw toe (stappen hierboven).

## Idee voor later

Dit lijkt een breder gevoeld gebrek in de plugin (er bestaat een gerelateerde, publieke
GitHub-issue over een andere beperking van dit zoekscherm — de 30-resultaten-limiet). Overweeg
deze verbetering als pull request voor te stellen bij `microsoft/moodle-local_o365`, zodat ze
uiteindelijk in de officiële plugin terechtkomt en je dit niet telkens manueel moet
hertoepassen.
