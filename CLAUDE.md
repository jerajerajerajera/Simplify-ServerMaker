<!-- GEMEENSCHAPPELIJK DEEL: in alle 6 Simplify-repo's letterlijk gelijk. Wijzig het overal tegelijk. -->
# Werkwijze Simplify

Simplify is één product: de kern `simplify` plus de add-ons `simplify-servermaker`, `-toolkit`, `-eggupdates`,
`-explain` en `-hosting`. De splitsing is alleen verpakking. Elke add-on volgt dezelfde UI en dezelfde regels.
Schrijf met de eigenaar in het Nederlands; code, README, CHANGELOG, commits en PR's in het Engels.

## 1. De opdracht: 0.7.0 is de norm

Simplify is een herbouw van PelicanSimplify 0.7.0 (snapshot.10) **met alle functionaliteit**. "Kleiner geschreven"
betekent minder code, nooit minder functies.

- De functielijst staat in de kernrepo: `docs/REFERENCE-070.md`. Elke regel is een functie uit 0.7.0, met de repo die
  hem levert en een status: `aanwezig`, `bewust weg (datum, akkoord eigenaar)` of `ontbreekt`.
- Deze lijst is de norm voor wat er moet bestaan. Een functie is pas `aanwezig` met een test die hem echt
  gebruikt (kolom **test**); zonder test is hij niet af.
- Een functie mag alleen `bewust weg` of `anders` zijn met het akkoord van de eigenaar erbij. Jij beslist dat nooit zelf.
- Raakt je wijziging een scherm of functie uit die lijst, vergelijk dan met 0.7.0 vóór je "klaar" zegt: dezelfde
  knoppen, menu-items, tabbladen en instellingen, en maten niet groter dan 0.7.0 + 10%.
- Bouw je iets opnieuw, maak dan eerst de lijst van wat het oude scherm kon, en pas dan de code.

## 2. Definitie van klaar

Een wijziging is pas klaar als alles hieronder waar is. Meld nooit "gebouwd" of "klaar" zonder dit.

1. **Lokale checks groen:** `bash tests/run.sh`, `pint --test`, `python3 tests/hub-check.py`.
2. **CI groen:** `checks.yml` met panel-smoke op elke Pelican-versie in de matrix.
3. **Updatepad getest** als er een migratie of instelling verandert: vanaf de oudste ondersteunde versie
   (en vanaf 0.7.0-bestanden voor de kern) naar de nieuwe, met ingevulde instellingen, niet alleen een verse installatie.
4. **Doorgeklikt in een echt paneel**, met data die op het echte paneel lijkt (veel servers, lange namen, een plugin die
   niet van Simplify is, een server die nooit gestart is, een draaiende console). Elke knop die je aanraakte is echt
   **geklikt**, niet alleen bekeken: het gevolg van de klik klopt, er ligt niets overheen (ook niet boven de console),
   en een klik in het menu laadt alleen het contentvak.
5. **Stijlaudit groen** (kernrepo, `audit.yml`) als je UI raakt, op d1440, laag1366 en phone390, licht én donker.
   Met `section=compare` geen scherm groter dan 0.7.0 + 10%.
6. **Screenshot van het echte scherm** na de wijziging, bij de melding. Een mock-up of demo is geen bewijs.

Kan een stap niet (geen toegang, CI-minuten op), zeg dan precies welke stap ontbreekt. Nooit "zou moeten werken".

## 3. Elke fout van het echte paneel: eerst een rode test

Vindt de eigenaar (of jij) een fout op het echte paneel, dan:

1. Schrijf eerst een test (unit, panel-smoke of audit) die de fout laat zien. Laat zien dat hij **rood** is op de
   versie met de fout.
2. Fix de fout. Laat zien dat dezelfde test nu **groen** is.
3. Zet bij de fix in de PR: welke test, rood op welke commit, groen op welke commit.

Een fix zonder test die eerst rood was, is niet af. Zo komt dezelfde fout niet terug.

## 4. UI-regels (hard; een overtreding is een fout, geen smaak)

- De pagina scrolt nooit; alleen het contentvak scrolt, binnen zijn ronding.
- Het contentvak is een kaart; knoppen en Save staan in de titelbalk. Geen vak-in-vak.
- Alleen Tabler-iconen. Geen grote rode knoppen; gevaarlijke acties in het ⋯-menu.
- Laden met placeholders, nooit een leeg of springend scherm.
- Menu-items en tabbladen laden alleen het contentvak (`wire:navigate`, console en websocket blijven staan).
  Een volledige paginalading na een menuklik is een fout.
- Menu's, pop-ups en dropdowns liggen boven alles: de console (xterm) gebruikt lagen tot z-index 10, dus ≥ 20.
  Test met een klik, niet met een screenshot: onder het midden van de knop moet de knop zelf liggen.
- Scrollbalken: dun (11px, duim 4px), binnen de ronding (14px boven/onder, 4px rand), in de kleur van de achtergrond
  (wit 16% donker, zwart 16% licht).
- Paginatitel: `[servernaam] - Simplify`.
- Servericoon: eigen server-icon.png > Pelican-servericoon > egg-icoon > grasblok (Minecraft) > kubus.
- **Create server** opent vanaf elke ingang (servermenu, overzicht, admin Servers) de Server Maker als Simplify-popup,
  nooit het formulier van Pelican.
- Groepen in het admin-menu (zoals "Simplify" en "Other plugins") klappen uit; een losse pagina draagt de pluginnaam.
- Serveroverzicht minstens zo compact als 0.7.0.
- Nieuwe UI of een gewijzigd scherm: eerst een proefscherm (artifact of demo) laten zien, pas bouwen na akkoord.

## 5. Code

- PSR-12 en `pint`. Elke functie in een eigen bestand. Schrijf zoals de code eromheen.
- Migraties: idempotent (`hasTable`/`hasColumn`), bestandsnaam bevat `simplify`, en **nooit** plugin-klassen
  (zoals `PanelSettings`) gebruiken: tijdens een update zijn die niet geladen. Alleen `DB`/`Schema`.
- Laravel-jobs: noem een methode nooit `queue()` (botst met de Dispatcher).
- Hub-regels: geen verboden functienamen (`tests/hub-check.py`), geen eigen updater of pluginbeheer, updates alleen
  via de Hub. De knop-klasse heet `Button`, niet `Link`.
- Teksten: alle 32 talen in dezelfde PR (`tests/lang.php`).
- Na een merge in CSS: accolades tellen.
- Geen `composer install` of `yarn install` in de cloudcontainer; het echte paneel draait op GitHub Actions.
- Schrijf in README, CHANGELOG, commits en PR's neutraal; nooit over "AI" of hoe de code tot stand kwam.

## 6. GitHub en releases

- Werk op een branch, open een PR, squash-merge met een nette titel. Nooit direct op `main`.
- Push, merge en release alleen met een uitdrukkelijk ja van de eigenaar voor die stap.
- Een release van de kern vereist vooraf: stijlaudit groen, upgradetest groen, functielijst bijgewerkt
  (`docs/REFERENCE-070.md`), en screenshots van de gewijzigde schermen.
- CI kost de eigenaar mails bij elke rode run: niet per poging pushen om CI te testen; test eerst lokaal.
- Nooit secrets, tokens, wachtwoorden of interne adressen in code, issues, PR's of output.
- Eén wijziging per PR. Liever drie kleine PR's dan één grote.

## 7. Melden

- Lever eerst een **volledige** lijst van bevindingen, niet in stukjes.
- Een vraag aan de eigenaar is één ja/nee-vraag met een aanbeveling.
- Geen tijdschattingen.
- Zeg eerlijk wat niet getest is.

## Deze repo: Server Maker (`simplify-servermaker`)

- Maakt een server in een paar stappen (spel, naam, resources, Create). Zonder Simplify werkt hij los; mét Simplify
  opent **elke** Create server-ingang hem als Simplify-popup in het frame, nooit het Pelican-formulier.
- EULA: optioneel bij aanmaken. Het verplichte vinkje komt pas bij de eerste start (in de kern), één keer.
- Eggs en bronnen: `resources/eggs/SOURCES.md`, index via `scripts/build-egg-index.py`.
- In de functielijst (`docs/REFERENCE-070.md` in de kern) horen hier o.a.: icoon, spelinstellingen, resources, back-ups,
  Steam-variabelen, "Let people make their own servers". Raak je een stap, vergelijk die stap met 0.7.0.
- Doorklikken in een echt paneel betekent hier: een server echt aanmaken tot hij installeert, als admin én als gewone
  gebruiker met een allowance, vanaf het overzicht, het servermenu en admin › Servers.
