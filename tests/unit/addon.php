<?php

// The Server Maker presents itself to Simplify with its id, a translated name and description, and an icon.

declare(strict_types=1);

require __DIR__.'/../bootstrap.php';

use Simplify\ServerMaker\ServerMakerAddon;

$addon = new ServerMakerAddon;
$manifest = json_decode((string) file_get_contents(__DIR__.'/../../plugin.json'), true);
check('the add-on id is the plugin id', $addon->id() === $manifest['id']);
check('the name is translated', $addon->name() === 'Server Maker', $addon->name());
check('the description is translated', ! str_contains($addon->description(), 'simplify-server-maker::'));
check('the icon is a Tabler icon', str_starts_with($addon->icon(), 'tabler-'));
echo "PASS add-on\n";
