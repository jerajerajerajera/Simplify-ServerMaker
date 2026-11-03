# Simplify Server Maker

A [Pelican](https://pelican.dev) plugin to create a game server in a few clear steps: pick Minecraft or another game,
give it a name, choose the resources and press Create. The Server Maker picks the egg, the port and the Docker image.

For Minecraft you pick the server type (Vanilla, Paper, Purpur, Fabric, Forge, NeoForge, Quilt, a modpack, Bedrock or
a proxy) and the version, and set the first settings: maximum players, game mode, difficulty and, for Java, the MOTD,
view distance, spawn protection, PvP, whitelist, allow flight and a server icon. Tick
the Minecraft EULA on the last step and you can start the server as soon as it is installed; leave it and it asks for
the EULA at its first start. Other games found in the Steam store can take their Steam banner and icon along.
Administrators can also pick any game from the official Pelican egg index. A draft is kept, so you can carry on later.

It works on its own. With [Simplify](https://github.com/jerajerajerajera/Simplify) installed you also get:

- Create server in Simplify opens the Server Maker inside Simplify, and its settings are linked from the add-on list;
- a colour for the new server, from the same colours as Simplify's colour menu;
- with [Simplify Toolkit](https://github.com/jerajerajerajera/Simplify-Toolkit) also installed, the MOTD maker with
  colours and a preview, and the new server's own automatic backup schedule;
- a subdomain for the new server, suggested from its name, on a port that is forwarded, when Simplify's subdomains
  are set up.

## Use

Install the plugin from the [Pelican Hub](https://hub.pelican.dev). Administrators find **Server Maker** in the
admin area. The gear button on that page opens the settings; with Simplify installed they open from Simplify's add-on list.

### Letting people make their own servers

Turn on **Users can make servers** in the settings and choose:

- which roles, which nodes and which eggs. Leave a list empty for all of them. **Minecraft (all types)** in the egg
  list gives the Server Maker's own Minecraft choices: Vanilla, Plugins, Mods, Modpack, Bedrock and Proxy;
- whether missing eggs may be added: Minecraft from the bundled eggs, other games from the official Pelican egg index
  (only while every egg is allowed). Off by default;
- the allowance per person: servers, RAM and disk over all their servers, CPU and backups per server.

Those people find the Server Maker in the normal panel. They only see the chosen nodes and eggs, and cannot go over
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
