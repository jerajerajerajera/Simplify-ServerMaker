# Bugregister Simplify Server Maker

> Elke gefikste bug staat hier, met de test die hem bewaakt (WR-11). Een terugkerende bug is een regressie: zoek
> eerst uit waarom de test hem niet ving. Een rij weghalen mag niet, de test weghalen ook niet. Nieuwe nummers geeft de
> repo-thread zelf met het prefix van deze repo (WR-8); Startvulling 2-11 (V35): de
> bugs die twee keer of vaker terugkwamen, uit CHANGELOG en projectnotities. "Nog geen test" is een open punt voor
> de repo-thread.

| Nr | Datum | Wat zag de gebruiker | Oorzaak | Fix (versie) | Regressietest | Teruggekomen | Regel |
|---|---|---|---|---|---|---|---|
| B14 | 1.4.1, 1.4.2 | Knoppen in de wizard in Filament-stijl, of Next/Back niet in het midden van hun knop | Knoppen niet via Simplify's knopstijl | 1.4.1, 1.4.2 | `tests/panel/smoke.php` "the Server Maker page renders, with Simplify buttons instead of bare Filament buttons"; centrering: audit `maker-*` | 1× | P-8 |
| B9 | 1.4.1 | Instellingspagina scrolt als geheel met Simplify | Formulier zonder `s-fit` | 1.4.1 | `tests/panel/smoke.php` (settings form has s-fit) | 0× (patroon met andere add-ons) | P-7 |
| B16 | 2-11 (Unreleased, R3) | Op Performance stond de hoofdpoort zonder "· main" | Blade-`@if` direct na `}}` rendert niet | #45 (bf7b543) | `tests/panel/smoke.php` "Performance gives a server extra ports…" (rood op 6419d6c) | 0× | P-27 |
| B17 | 2-11 (Unreleased, R3) | Extra poorten gaven altijd "not enough free ports" | In het serverpaneel scoopt Filament `Allocation`-queries op de tenant-server; vrije poorten waren onzichtbaar | #45 (d2114f0): `Allocation::withoutGlobalScopes()` in `ExtraPorts` en `Ports::free` | `tests/panel/smoke.php` "Performance gives a server extra ports…" (rood op 9c215b3) | 0× | P-27 |
| SM-B1 | 2-11 (Unreleased, M02) | De Steam-schakelaar "automatisch tokens maken" sloeg zichzelf op; instellingen en Performance vroegen niets bij verlaten met wijzigingen | `afterStateUpdated` sloeg direct op; formulieren zonder `data-simplify-unsaved` | #52 | `tests/panel/smoke.php` "nothing saves itself…" en "the Steam row saves only from its popup…" + check in "Performance gives a server extra ports…" (rood op 4d358e8) | 0× | P-38 |
| SM-B2 | 3-11 (kern-audit tablet768, V52) | Op een tablet in de admin-kaart naast het menu (~430px) liep de stappenbalk weg: alleen "1 Game" zichtbaar | Stijlen schakelden op vensterbreedte (`@media`), niet op de breedte van de Server Maker | #54: container query op `.smk` | `tests/steps.cjs` (rood op 6422a9d) | 0× | P-27 |

## Patronen

Bugs met dezelfde oorzaak, en de regel of check die het hele patroon voorkomt.
- **Pagina scrolt** (B9: kern 6×, Toolkit, Server Maker, Explain, Hosting): P-7. Elke instellingspagina van een add-on
  heeft `s-fit` en een smoke-stap die dat controleert; elk kernscherm een audit `scroll-*`.
- **Updatepad** (B11, kern 1.4.4 en 1.5.1): P-28. Upgradetest met echte 0.7.0-instellingen in `checks.yml`.
- **Testpaneel lijkt niet op het echte paneel** (B15 Egg Updates 1.1.3/1.1.4, kern-audit): P-31. Smoke en audit bootsen
  het echte paneel na (volle nodes, echte eggs, 20+ servers, nep-plugin).
