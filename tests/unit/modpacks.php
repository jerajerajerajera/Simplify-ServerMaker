<?php

// Modpack keys, mixing the sites, Modrinth through a fake network, and the Technic list.

declare(strict_types=1);

require __DIR__.'/../bootstrap.php';

use Simplify\ServerMaker\Games\BundledEggs;
use Simplify\ServerMaker\Modpacks\Modrinth;
use Simplify\ServerMaker\Modpacks\Pack;
use Simplify\ServerMaker\Modpacks\Remote;
use Simplify\ServerMaker\Modpacks\Sources;
use Simplify\ServerMaker\Modpacks\Technic;

check('a Modrinth key splits', Pack::split('modrinth:AANobbMI') === ['modrinth', 'AANobbMI']);
check('an FTB id is a number', Pack::split('ftb:abc') === null && Pack::split('ftb:79') === ['ftb', '79']);
check('an unknown site is refused', Pack::split('evil:1') === null);
check('a path in the id is refused', Pack::split('modrinth:../../x') === null);

$card = fn (string $source, string $id) => new Pack($source, $id, $id, '', null, 'https://example.com');
$mixed = Sources::interleave(['a' => [$card('modrinth', 'a1'), $card('modrinth', 'a2')], 'b' => [$card('ftb', '1')]], 10);
check('sites are mixed', array_map(fn (Pack $pack) => $pack->id, $mixed) === ['a1', '1', 'a2']);
check('the mix stops at the limit', count(Sources::interleave(['a' => [$card('modrinth', 'a1'), $card('modrinth', 'a2')]], 1)) === 1);
check('the newest release wins', Sources::newestRelease(['1.19.2', '1.20.1', '23w31a', '1.20']) === '1.20.1');
check('the pack fills in the egg', Sources::require(['PROJECT_ID' => '', 'VERSION_ID' => ''], ['PROJECT_ID' => 'x', 'VERSION_ID' => 'y']) === ['PROJECT_ID' => 'x', 'VERSION_ID' => 'y']);
check('an egg without the variable is refused', failsOn(fn () => Sources::require(['OTHER' => ''], ['PROJECT_ID' => 'x'])) === 'data.modpack');

$asked = [];
Remote::$transport = function (string $url, array $query) use (&$asked): array {
    $asked[] = $url;

    return match (true) {
        str_ends_with($url, '/search') => ['hits' => [['project_id' => 'AAA', 'slug' => 'fabulous', 'title' => 'Fabulous <b>Pack</b>', 'description' => 'Fun', 'icon_url' => 'http://insecure.example/icon.png']]],
        str_ends_with($url, '/project/AAA/version') => [['id' => 'v2', 'version_number' => '2.0'], ['id' => 'v1', 'version_number' => '1.0']],
        str_ends_with($url, '/version/v2') => ['project_id' => 'AAA', 'game_versions' => ['1.20.1', '1.20.4']],
        str_ends_with($url, '/version/v9') => ['project_id' => 'OTHER', 'game_versions' => ['1.12.2']],
        default => [],
    };
};
$modrinth = new Modrinth;
$found = $modrinth->search('fab');
check('Modrinth finds the pack', count($found) === 1 && $found[0]->key() === 'modrinth:AAA');
check('HTML is removed from titles', $found[0]->title === 'Fabulous Pack');
check('an http icon is dropped', $found[0]->icon === null);
check('the link goes to the pack', $found[0]->url === 'https://modrinth.com/modpack/fabulous');
check('versions are listed', $modrinth->versions('AAA') === ['v2' => '2.0', 'v1' => '1.0']);
check('the version tells the Minecraft release', $modrinth->runtime('AAA', 'v2') === ['minecraft' => '1.20.4']);
check('a version of another pack tells nothing', $modrinth->runtime('AAA', 'v9') === []);
check('the Modrinth egg gets the ids', $modrinth->environment('AAA', 'v2', ['PROJECT_ID' => '', 'VERSION_ID' => '']) === ['PROJECT_ID' => 'AAA', 'VERSION_ID' => 'v2']);
Remote::$transport = null;

$technic = new Technic;
$manifest = BundledEggs::manifest();
foreach (Technic::catalog() as $item) {
    check("{$item['id']} has its bundled egg", ($manifest[$item['egg_file']]['name'] ?? null) === $item['egg_name']);
    check("{$item['id']} has one version", array_keys($technic->versions($item['id'])) === ['egg_default']);
}
check('Technic is searched by name', array_map(fn (Pack $pack) => $pack->id, $technic->search('hexxit')) === ['hexxit']);
check('Technic knows the Java of a pack', $technic->runtime('tekkit', 'egg_default')['java'] === 8);
foreach (['egg-modrinth-generic.json', 'egg-ftb-server.json', 'egg-curseforge-generic.json'] as $file) {
    check("$file is bundled", isset($manifest[$file]) && BundledEggs::definition($file) !== []);
}
echo "PASS modpacks\n";
