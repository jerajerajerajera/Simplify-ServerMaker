<?php

// Server software: which Minecraft Java software a server runs, from its egg.

declare(strict_types=1);

require __DIR__.'/../bootstrap.php';

use Simplify\ServerMaker\Software\CurrentSoftware;

check('Paper is Plugins', CurrentSoftware::of('Paper') === ['type' => 'plugins', 'software' => 'paper']);
check('Forge Minecraft is Mods', CurrentSoftware::of('Forge Minecraft') === ['type' => 'mods', 'software' => 'forge']);
check('Vanilla Minecraft is Vanilla', CurrentSoftware::of('Vanilla Minecraft') === ['type' => 'vanilla', 'software' => null]);
check('a modpack egg is Modpack', CurrentSoftware::of('Modrinth Generic')['type'] === 'modpack');
check('a bundled pack without a telling name is Modpack', CurrentSoftware::of('The 1.12.2 Pack')['type'] === 'modpack');
check('Spigot is other Java software', CurrentSoftware::of('Spigot') === ['type' => 'other', 'software' => null]);
check('Bedrock is left out', CurrentSoftware::of('Vanilla Bedrock') === null);
check('proxies are left out', CurrentSoftware::of('Velocity') === null && CurrentSoftware::of('Bungeecord') === null);
check('other games are left out', CurrentSoftware::of('Rust') === null && CurrentSoftware::of('Paperboy') === null);
check('the version comes from the egg variables', CurrentSoftware::version(['BUILD_NUMBER' => 'latest', 'MINECRAFT_VERSION' => '1.21.1']) === '1.21.1');
check('latest is no version', CurrentSoftware::version(['MINECRAFT_VERSION' => 'latest']) === null);

echo "PASS software\n";
