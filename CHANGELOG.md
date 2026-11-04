# Changelog

## Unreleased

- A Paper server can be made, and a server switched to Paper, on a panel with Pelican's own Paper egg. Its User
  Agent is required, has no default and is hidden; it now gets Server Maker's name, or keeps the server's own value on
  a change of software. When another required value is missing, the Server Maker shows Pelican's message instead of
  doing nothing.
- With Simplify-Explain, the errors **The server could not be created**, **The software could not be changed** and
  **No token created** get an Explain button. Only the person who got the error and root admins can explain it; the
  technical details go to root admins only.
- With Simplify 2.0 (add-on API 8), the Server Maker settings open in Simplify's settings › Add-ons, with its Save
  bar, and admin › Server Maker is no longer in the menu. Without Simplify the settings page stays as it was.
- Checks: PHPStan (level 5) runs in CI, and the panel smoke also runs with the real Simplify core (main) instead of a stub.
- Uninstalling the Server Maker keeps its settings and drafts, so installing it again brings them back. Before, an
  uninstall removed them.
- Checks: CI updates from 1.4.2 (the Hub's stable version) and from 2.0.0-beta.4 with every setting filled in, and puts
  the old version back over the new tables to see its pages still open.

## 2.0.0-beta.4

- Version aligned with Simplify 2.0.0-beta.4.

## 2.0.0-beta.3

- Clearer settings for who may make servers. The switch is now **Users can make servers**, and an empty list of roles,
  nodes or eggs means all of them (nodes no longer have to be chosen). Minecraft is one entry in the list of eggs,
  **Minecraft (all types)**, instead of its own switch next to "Other games".
- **Add missing eggs**: in Simplify's friends mode, while every egg is allowed, people may also add games from the
  official Pelican egg index. Otherwise only administrators add those.
- Updating keeps the old choice: Minecraft and the other games chosen before end up in the one list, and a setup that
  let nobody make a server (no nodes, or no game at all) has the switch turned off.
- Steam is a row on Simplify's **Connections** page in the account menu (Simplify 2.0 and later) instead of its own
  icon. **Connect key** or **Change** opens a popup with the key and **Create tokens automatically**, saved with its
  Save button only; an empty key field keeps the current key. **Disconnect** is in the ⋯ menu. Without Connections
  the same row is on the Steam page.

## 2.0.0-beta.1

- First version on the Hub. The same as 1.5.0; a download of the repository holds the plugin only, without its docs,
  tests and scripts (0.3 MB instead of 1.7 MB).

## 1.5.0

- The Server Maker follows the width it gets instead of the window: in a narrow card (an admin page next to the menu
  on a tablet) the step bar takes the compact phone form instead of cutting steps off.
- Nothing saves itself: the Steam switch waits for Save, and Steam, Performance and the settings show "Changes made"
  with Save and Discard and ask before you leave with changes.
- Extra ports per server: the Performance page lists a server's ports, the main one first, and gives it extra ports
  (for a voice chat, a map or a query port) from the free ports of its machine, right after the main port when it can.
  Fewer ports removes the newest ones; the main port never changes. Administrators up to 10; owners who may make
  servers themselves up to the new "Extra ports per server" allowance (0 by default).
- Steam: connect your own Steam Web API key once (in Simplify's account menu, or Pelican's user menu without
  Simplify), as in Simplify 0.7.0. Games that need a login token per server (CS2, Unturned, Insurgency: Sandstorm …)
  then get one with one click in the Server Maker, or automatically when the field is left empty. The key is stored
  encrypted and never shown again; only its last four characters.
- New Performance page on every server, as in Simplify 0.7.0: the CPU, RAM and disk limits, and new ones with the
  same choices as the Server Maker. Administrators change them freely; owners who may make servers themselves within
  their allowance; everyone else sees the limits only. New limits apply after the next restart.
- New Server software page on Minecraft Java servers, as in Simplify 0.7.0: switch to other software, another
  Minecraft version or a modpack, with the same choices as the Server Maker. Only the owner or an administrator, with
  the server stopped, a successful backup from the last 30 minutes and the server name typed to confirm. The old
  startup settings are kept and put back when the reinstall fails.
- The settings have a Starting resources table: the CPU, RAM and disk a new Minecraft server starts with, per type
  (Vanilla, Plugins, Mods, Modpack, Bedrock, Proxy), as in Simplify 0.7.0. The Resources step starts from these
  values, within each person's allowance.

## 1.4.2

- Next and Back sit in the middle of their buttons again.
- In Simplify's Create server popup the wizard runs to the popup's edges: no second padding around it.
- With Simplify installed there is no Settings button above the wizard any more (it opened Pelican's admin inside the
  popup); the settings open from Simplify's add-on list. Without Simplify the button stays.

## 1.4.1

- Back, Next, Create and Edit settings in the Server Maker use Simplify's button style instead of Filament's.
- With Simplify installed, the Server Maker settings page fits the window: only the form scrolls, never the page.

## 1.4.0

- With Simplify installed, the Server step lets you pick the server's colour, from the same colours as Simplify's
  colour menu (as in Simplify 0.7.0). Needs a Simplify version with its colour list.
- With Simplify Toolkit's automatic backups on, the Resources step can give the new server its own backup schedule
  (interval, recovery points, skip while it has not run), as in Simplify 0.7.0. Off keeps the panel's schedule.
- Minecraft Java servers get the rest of their first settings on the Minecraft step: MOTD, view distance, spawn
  protection, PvP, whitelist and allow flight. With Simplify Toolkit installed the MOTD uses its MOTD maker with colours
  and a preview.
- Other games found in the Steam store offer to copy their Steam banner and a square icon to the new server, as in
  Simplify 0.7.0. The icon becomes the server icon; with Simplify the banner becomes the server's banner. Turn it off
  under Server Maker settings with Offer Steam banners and icons.

## 1.3.3

- Create server got stuck on the Review step. The Minecraft EULA box is optional now: tick it and the server starts
  straight away after installing, or leave it and the server installs anyway and asks for the EULA when you start it.
- Opened from Simplify's Create server, the Server Maker sends you to the new server as a whole page, not inside
  Simplify's window.

## 1.3.2

- Minecraft Java servers ask for the Minecraft EULA on the Review step: a box you tick yourself, with a link to the
  official EULA (minecraft.net/eula). It is required, so Create server waits for it, and eula.txt is written as soon
  as the installation is done, so the server starts right away.

## 1.3.1

- Create server showed "Error while loading page" after the server was made, and the first settings (players, game
  mode, difficulty, icon) were not applied: the job's helper was called queue(), a name Laravel's dispatcher uses
  itself. It has its own name now, the settings travel with the job instead of a separate table, and a problem there
  never stops the Server Maker from opening the new server.

## 1.3.0

- Minecraft servers get their first settings in the Server Maker again, as in Simplify 0.7.0: maximum players, game
  mode and difficulty, and for Java a server icon (a 64 × 64 PNG). They are written to server.properties and
  server-icon.png as soon as the installation is done, and shown on the Review step.

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
