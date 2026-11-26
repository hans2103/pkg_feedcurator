# Ontwerp: pkg_feedcurator — onderwerpgebonden nieuws uit RSS, met goedkeuring per mail

Datum: 2026-11-26 · Eerste gebruiker: gehandicaptenvalkenswaard.nl · Status: concept

## Waarom

Het laatste eigen nieuwsbericht op gehandicaptenvalkenswaard.nl is van december 2022; de site oogt daardoor
stil, terwijl de gemeente en landelijke organisaties wel nieuws over het onderwerp publiceren. Hans wil dat
nieuws automatisch binnenhalen, **alleen als het over het onderwerp van de site gaat**, altijd met verwijzing
naar de bron. Een bestuurslid beslist per bericht of het online komt, met één klik vanuit een mail.
De oplossing moet ook bij andere sites (andere gemeenten, andere onderwerpen) inzetbaar zijn, met bronnen die
je zonder code toevoegt.

## Doelen

- Dagelijks RSS-feeds ophalen; alleen berichten die op het onderwerp passen (trefwoordfilter) worden een
  Joomla-artikel, als **concept** (niet gepubliceerd).
- Na een run met nieuwe concepten krijgt het bestuur één mail met per bericht een link naar een
  beoordelingspagina: titel, samenvatting, bron en de knoppen **Publiceren** en **Weggooien**. Geen login nodig.
- Bronnen, trefwoorden, categorie en ontvangers zijn instellingen; geen code per site.
- Los herbruikbaar pakket, gebouwd en uitgebracht zoals de andere HKweb-extensies.

## Niet-doelen

- Geen volledige tekst of afbeeldingen van de bron overnemen (auteursrecht): titel, korte samenvatting, link.
- Geen scraping van sites zonder feed (Valkenswaard24, Rijksoverheid, Agenda 22).
- Geen AI-samenvatting of AI-beoordeling van relevantie.
- Geen herinneringsmails, geen beheerscherm buiten wat Joomla al heeft (artikelbeheer, Scheduled Tasks).
- Geen bijwerken van een bericht als de bron het later wijzigt.

## Anti-doelen (dan is het mislukt, ook als het technisch werkt)

- Een bericht staat online zonder dat een mens het koos. Concreet: een linkscanner in Outlook/Gmail die de
  mail-link opent mag **nooit** iets publiceren of weggooien.
- Berichten die niet over het onderwerp gaan komen als concept binnen in zulke aantallen dat het bestuur de
  mails gaat negeren.
- Een weggegooid bericht komt bij een volgende run terug.
- De pagina's lezen als kopie van de bron (SEO-duplicaat of auteursrechtprobleem).

## Aanpakken (overwogen)

| | Aanpak | Afweging |
|---|---|---|
| **A (gekozen)** | Eigen Joomla-pakket in een eigen repo, zoals `pkg_informanagement` | Herbruikbaar, update via Joomla, past in bestaande tooling. Meer werk vooraf. |
| B | Plugin alleen in de site-repo | Sneller, maar kopiëren per site; lopen uit elkaar. Strijdig met de wens tot hergebruik. |
| C | Externe dienst (Worker) die via de Joomla-API artikelen maakt | Ook voor niet-Joomla, maar extra infrastructuur en API-sleutel per site. |

Hans koos A, met de opdracht het **op dezelfde manier te bouwen als de andere extensies**. Referentie:
`~/Development/Repos/pkg_informanagement` (pakket met taakplugin die een feed in artikelen omzet).

## Opbouw (volgt pkg_informanagement)

Nieuwe publieke repo `hans2103/pkg_feedcurator` (naam: mijn voorstel). Zelfde indeling, tooling en afspraken als
pkg_informanagement: `Src/`, `package.xml` (enige bron van de versie, calendar versioning `YY.WW.PATCH`),
`package.sh`, `package_script.php`, `scripts.conf`, `bin/` byte-identiek uit de referentie, `AIdocs/`,
`tests/unit/` (plain PHP, geen PHPUnit) en `tests/shell/`, `.githooks/`, `.github/workflows/{validate,release}.yml`,
`cliff.toml`, `updates.xml` op `main` als update-server via GitHub Releases. Branches `main`/`develop`/`feature/…`,
Nederlandse conventional commits met issuenummer, CLAUDE.md en README in het Engels, code-docblocks Engels,
Joomla coding standard, `declare(strict_types=1)`, `@since __DEPLOY_VERSION__`. Vereisten Joomla 5.2+ / 6, PHP 8.3+.

Twee extensies, alleen samen te verwijderen (`blockChildUninstall`); `package_script.php` zet ze aan bij installatie.

### 1. plg_task_feedcurator — routine `feedcurator.import`

Instellingen (`forms/import.xml`, per geplande taak):

- `sources` (subform, herhaalbaar): `name` (getoond als bron), `url` (https), `filter` (aan/uit, standaard aan),
  `keywords` (extra trefwoorden voor deze bron), `exclude` (trefwoorden die een bericht uitsluiten), `enabled`.
- `keywords`: algemene trefwoordenlijst (één per regel), geldt voor alle bronnen met `filter` aan.
- `catid` (verplicht), `created_by` (leeg = eerste Super User), `max_age_days` (standaard 30: oudere items
  negeren), `max_per_run` (standaard 10).
- `first_run_weeks` (standaard 0 = uit, maximaal 52): **eenmalig terugkijken**. Bij de eerste geslaagde run
  van een bron gebruikt de taak deze periode in plaats van `max_age_days`, en `max_per_run` geldt dan niet,
  zodat er niets verloren gaat. Dat geldt per bron: een bron die je later toevoegt, kijkt ook eenmalig terug.
  Ook dan krijgen de artikelen de datum van het oorspronkelijke bericht. Een feed bevat meestal alleen zijn
  laatste 10 tot 25 berichten, dus verder terug dan wat de feed nog aanbiedt kan niet.
- `notify_groups` (gebruikersgroepen): ontvangers van de mail. `site_url` (verplicht): basis voor de links.
- `link_days` (standaard 7): geldigheid van de beoordelingslink.
- `notify_after` (standaard 3): zoals bij informanagement, pas na zoveel mislukte runs op rij KNOCKOUT.

Filter: een item past als (bij `filter` aan) minstens één trefwoord (algemeen + bron) voorkomt in titel,
samenvatting of RSS-categorieën, en geen `exclude`-woord. Hoofdletterongevoelig, op woordgrens, met `*` als
voorvoegsel-joker (`toegankelijk*` vindt ook "toegankelijkheid"). Een bron met `filter` uit neemt alles over.

Per item dat past en nog niet bekend is (op `guid`, anders `link`):

- Artikel met `state = 0` in `catid`; titel uit de feed; introtekst = samenvatting uit `description`
  (HTML gestript, WordPress-regel "Het bericht … verscheen eerst op …" verwijderd, max. ±300 tekens op een
  woordgrens), gevolgd door een bronregel: `Bron: {name}, {datum} — Lees het volledige bericht` met link naar
  het origineel (`rel="noopener"`, opent extern). Geen fulltext, geen afbeeldingen.
- `created`/`publish_up` = `pubDate` van het item; meta robots `noindex, follow`; `note = feedcurator:{bron}`.
- Opslaan zoals pkg_informanagement: via `Table\Content`, met workflow-koppeling en
  `onContentBeforeSave`/`onContentAfterSave`.
- Regel in `#__feedcurator_items` (`guid_hash` PK, `source`, `article_id`, `imported`). Een item met een
  regel komt nooit opnieuw binnen, ook niet als het artikel is weggegooid of verwijderd.

Na de run: zijn er nieuwe concepten, dan één mail (Joomla-mailer, sitetaal) aan alle actieve, niet-geblokkeerde
leden van `notify_groups`: aantal, en per bericht titel, bron en een persoonlijke link.
Fouten per bron worden gelogd; een onbereikbare bron laat de andere bronnen doorlopen.

`src/Feed/` (parser, filter, mapper) blijft vrij van Joomla-aanroepen, zodat de unit-tests zonder Joomla draaien.
Parser met `LIBXML_NONET`, RSS 2.0 en Atom.

### 2. plg_system_feedcurator — beoordelingspagina

- Link: `/index.php?option=com_ajax&group=system&plugin=feedcurator&format=html&a={artikel}&r={ontvanger}&e={verloop}&s={handtekening}`.
  Handtekening = HMAC-SHA256 over artikel, ontvanger (gebruikers-id) en verlooptijd, met een sleutel afgeleid van
  de `secret` uit `configuration.php`. Niets opgeslagen; ongeldig of verlopen → nette melding.
- **GET toont alleen**: titel, samenvatting, bron + link, huidige status, en een formulier met twee knoppen.
  GET verandert nooit iets (bescherming tegen linkscanners).
- **POST** (met dezelfde gesigneerde velden + Joomla-formuliertoken) voert uit: Publiceren → `state = 1`;
  Weggooien → `state = -2` (prullenbak). Alleen als het artikel nog concept is; anders melding
  "al gepubliceerd/weggegooid". De actie draait onder de identiteit van de ontvanger, dus Joomla zet de juiste naam in het actielog.
- Pagina in de sitetaal, eenvoudig en toegankelijk (WCAG): grote knoppen, duidelijke tekst, werkt op mobiel.

## Inrichting op gehandicaptenvalkenswaard.nl (in deze site-repo, na de eerste release)

- Pakket installeren via Joomla. Subcategorie "Uit de regio en het land" onder Nieuws.
- Bronnen: gemeente Valkenswaard (`https://www.valkenswaard.nl/rss/content-list?type%5B%5D=news&sort_by=published_at&sort_order=DESC`,
  filter aan), Ieder(in) (`https://www.iederin.nl/feed/`, filter aan), Coalitie voor Inclusie
  (`https://www.coalitievoorinclusie.nl/feed/`, filter aan).
- Startlijst trefwoorden (te verfijnen met het bestuur): beperking*, gehandicapt*, handicap*, toegankelijk*,
  rolstoel*, inclusie*, inclusief, slechtziend*, blind*, doof*, slechthorend*, Wmo, mantelzorg*,
  Agenda 22, VN-verdrag, Oog voor Iedereen, gemeentepolis, chronisch ziek*, hulpmiddel*, gehandicaptenparkeer*.
- Taak dagelijks; ontvangers: groep Bestuur (bestaan en leden te controleren).
- `/nieuws` en de kaart-layout tonen bij overgenomen berichten de bron; eigen berichten blijven herkenbaar.
- Controleren dat de Joomla-scheduler op live echt draait (cron of lazy scheduler).

## Beslissingen na de bouwerscheck (mijn keuzes, door Hans te controleren)

- **Workflows:** publiceren en weggooien lopen via de `publish()` van het com_content-artikelmodel,
  dus niet door `state` direct te schrijven. Zo vuurt `onContentChangeState` en houdt Joomla de rechten aan.
  Versie 1 ondersteunt sites waar de workflows van com_content uit staan, wat de standaard is. Staan ze
  aan, dan weigert Joomla de statuswijziging. De pagina meldt dan dat het bericht via de beheeromgeving
  gepubliceerd moet worden en wijzigt zelf niets.
- **Ontvangers zijn Joomla-gebruikers** uit `notify_groups`, dus niet langer losse adressen: `notify_emails`
  vervalt. De link is ondertekend voor het gebruikers-id in plaats van het e-mailadres. De POST draait onder
  de identiteit van die gebruiker. Daardoor gelden Joomla's eigen rechten (`core.edit.state`) en staat de
  juiste naam in het actielog. Heeft die gebruiker geen recht om te publiceren, dan meldt de pagina dat.
- **Herkenning van items:** een item is uniek op zijn genormaliseerde `link` (de `guid` als er geen link is).
  Daardoor wordt hetzelfde bericht uit twee bronnen één artikel.
- **Sitelink in de mail:** de taak draait vaak via cron op de commandline, waar Joomla de site-URL niet kent.
  Daarom komt er een verplichte taakinstelling `site_url`.
- **Mail:** één mail per ontvanger, omdat elke link persoonlijk is. Items boven `max_per_run` schuiven door
  naar de volgende run. Een bericht dat via twee bronnen binnenkomt (dezelfde `link`) wordt één artikel.
- **Repo en release:** de repo wordt eerst lokaal opgezet. Pas na een expliciet akkoord van Hans
  maak ik de publieke GitHub-repo aan en breng ik de eerste versie uit.
- **Inrichting van de site** (zie de sectie hierboven) is een apart plan, uit te voeren na de eerste release.
  Het templatewerk herkent overgenomen berichten aan hun subcategorie; de bron staat al in de bronregel van
  de introtekst. Daarom maakt het pakket geen extra custom field.

## Aan de bouwer

Klassenamen en bestandsindeling binnen `src/`, opmaak van mail en beoordelingspagina, tests per onderdeel,
exacte taalstrings (en-GB en nl-NL, contracttest zoals in de referentie), en de AIdocs-pagina's.
