# Simplify Server Maker

A [Pelican](https://pelican.dev) plugin to create a game server in a few clear steps: pick Minecraft or another game,
give it a name, choose the resources and press Create. The Server Maker picks the egg, the port and the Docker image.

It works on its own. Two other plugins add to it when they are installed:

- [Simplify](https://github.com/jerajerajerajera/Simplify): a **Create server** button on the Simplify dashboard and a
  link to the settings in its add-on list.
- [Simplify Subdomains](https://github.com/jerajerajerajera/Simplify-subdomains): a subdomain for the new server,
  suggested from its name, on a port that is forwarded.

## Use

Install the plugin from the [Pelican Hub](https://hub.pelican.dev). Administrators find **Server Maker** in the
admin area. The gear button on that page opens the settings.

### Letting people make their own servers

Turn on **Let people make their own servers** in the settings and choose:

- which roles may do it (empty for everyone with an account), on which nodes, with Minecraft and which other games;
- the allowance per person: servers, RAM and disk over all their servers, CPU and backups per server.

Those people find the Server Maker in the normal panel. They only see the chosen nodes and games, and cannot go over
their allowance. Administrators who may create servers in Pelican can always use everything.

### CurseForge

CurseForge modpacks need an API key from [console.curseforge.com](https://console.curseforge.com). Paste it in the
settings. It is stored encrypted and never shown again.

## What it sends where

- **Mojang** (`piston-meta.mojang.com`): the list of Minecraft releases and the Java version each one needs. No
  data about your panel is sent. Answers are cached for six hours.
- **Modrinth** (`api.modrinth.com`), **FTB** (`api.feed-the-beast.com`, `api.modpacks.ch`) and, only when you add an
  API key in the settings, **CurseForge** (`api.curseforge.com`): searching modpacks and listing their versions, when
  someone picks Modpack. Only the search words are sent. The browser shows the packs' icons from those sites.
  Technic packs come from a list in the plugin.

## Bundled eggs

The Minecraft eggs (Vanilla, Paper, Purpur, Fabric, Forge, NeoForge, Quilt, Bedrock, Velocity, BungeeCord and the
modpack eggs for Modrinth, CurseForge, FTB and ten Technic packs) come
from [pelican-eggs/minecraft](https://github.com/pelican-eggs/minecraft) (MIT, see `resources/eggs/LICENSE`). When
the panel has no egg for the chosen type, the Server Maker adds the bundled one with Pelican's own egg importer. It
never changes an egg that is already on the panel. The eggs' install scripts download the server software on the
node, as every egg does.

## License

Source available, see [LICENSE.md](LICENSE.md). Copyright (c) 2026 Jeroen (jerajerajerajera).
