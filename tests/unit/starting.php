<?php

// The CPU, RAM and disk a new Minecraft server starts with, as set in the Server Maker settings.

declare(strict_types=1);

require __DIR__.'/../bootstrap.php';

use Simplify\ServerMaker\Creation\StartingResources;
use Simplify\ServerMaker\Games\Minecraft;

check('nothing saved: the built-in defaults', StartingResources::pick('mods', []) === Minecraft::RESOURCES['mods']);
check('saved values are used', StartingResources::pick('mods', ['mods' => ['cpu' => 300, 'ram' => 8, 'disk' => 40960]]) === ['cpu' => 300, 'ram' => 8, 'disk' => 40960]);
check('another type keeps its defaults', StartingResources::pick('vanilla', ['mods' => ['cpu' => 300]]) === Minecraft::RESOURCES['vanilla']);
check('values out of range are ignored', StartingResources::pick('proxy', ['proxy' => ['cpu' => 0, 'ram' => 999, 'disk' => 100]]) === Minecraft::RESOURCES['proxy']);
check('only whole numbers', StartingResources::pick('proxy', ['proxy' => ['cpu' => '200', 'ram' => 2.5]]) === Minecraft::RESOURCES['proxy']);
check('other games start at 100 % / 4 GB / 10 GB', StartingResources::for(null) === ['cpu' => 100, 'ram' => 4, 'disk' => 10240]);

$saved = StartingResources::fromTable(['mods' => ['cpu' => '300', 'ram' => '8', 'disk' => '40'], 'proxy' => ['cpu' => '9999', 'ram' => '', 'disk' => '0']]);
check('the table is saved with disk in MB', $saved['mods'] === ['cpu' => 300, 'ram' => 8, 'disk' => 40960]);
check('too high is clamped, empty keeps the default', $saved['proxy'] === ['cpu' => 1600, 'ram' => 2, 'disk' => 5120]);
check('every type is saved', array_keys($saved) === Minecraft::TYPES);

echo "PASS starting\n";
