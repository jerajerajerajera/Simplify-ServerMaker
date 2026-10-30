# Changelog

## 1.2.0

- The Server Maker looks and works like it did in Simplify 0.7.0 again:
  - one bar at the bottom with Back, the steps (click a step you have done to go back to it), the saved draft with
    Discard, and Next. On a phone the bar shows the current step; tap it for the list of steps.
  - the Game step shows Minecraft in its own block with its server types; picking a type moves on by itself. Other
    games are listed below with their initials, with a search on longer lists.
  - administrators see every game from the official Pelican egg index too, with All, Popular and Installed filters.
    A game that is not on the panel yet is downloaded, checked against its published checksum and added when you
    press Next, with an Installing screen in between.
  - the Minecraft step shows the server types as cards with an icon, so you can still switch type (Proxy is here).
  - RAM, CPU and disk are one grid of equal cards with a small icon next to the amount.
  - the Review step is a server overview in two columns; administrators open Edit settings under it for the egg, Java
    version, Docker image, backups, startup command, environment variables and install script.
  - cards have a subtle background again, so buttons and the picked choice stand out.
- Start over above the Server Maker is now Discard in the bar at the bottom.

## 1.1.2

- The Server Maker is one box that fits the window: the steps stay on top and Back and Next at the bottom, and only the
  step in between scrolls. Before, the whole page scrolled, with announcements and the title moving out of view.

## 1.1.1

- Installing Server Maker from Admin → Plugins or the Hub now also removes the old copy right away. Those installs run in the
  panel's queue, where 1.1.0 only removed it once the queue worker restarted.

## 1.1.0

- The plugin id is now `simplify-servermaker` (it was `simplify-server-maker`), like the other Simplify plugins.
  Installing this version over the old one keeps every setting and draft and removes the old copy, so the panel
  never shows Server Maker twice.

## 1.0.5

- The Settings and Start over buttons above the Server Maker are Simplify icon buttons (a quiet bordered square, accent
  colour on hover) instead of plain Pelican icons, like the rest of Simplify.

## 1.0.4

- Simplify 1.3's setup now also asks who may make servers and how much, so a new panel is set up in one go.
  On older Simplify versions, or without Simplify, nothing changes.

## 1.0.3

- Works with the subdomains built into Simplify 1.2. The separate Simplify Subdomains plugin still works too.

## 1.0.2

- Server Maker now sits in the Simplify group of Pelican's admin menu, together with the other Simplify add-ons,
  in the same order everywhere. This works with or without the Simplify core.

## 1.0.1

- Simplify's add-on list shows the same icon as the admin menu.
- Ctrl+S (Cmd+S on a Mac) saves the settings.

## 1.0.0

First version as its own plugin.

- A wizard in five steps: game, game settings, server, resources and review.
- Minecraft: Vanilla, Plugins (Paper, Purpur), Mods (Fabric, Forge, NeoForge, Quilt), Modpack, Bedrock and Proxy
  (Velocity, BungeeCord), with the right Java version for each Minecraft version.
- Modpacks from Modrinth, FTB, Technic and CurseForge (with an API key).
- Every other game on the panel, with the egg's own settings.
- Bundled Minecraft eggs, added with Pelican's egg importer when the panel has none.
- Drafts: carry on where you left off.
- Settings: let people make their own servers within an allowance, on chosen nodes and games.
- Works with Simplify (Create server on the dashboard) and Simplify Subdomains (a subdomain for the new server).
- In 32 languages.
