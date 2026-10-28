<?php

// The allowance for people who are not administrators.

declare(strict_types=1);

require __DIR__.'/../bootstrap.php';

use Simplify\ServerMaker\Access;

$limits = ['max_servers' => 2, 'max_ram' => 8, 'max_disk' => 40, 'max_cpu' => 200, 'max_backups' => 2];
$left = Access::left($limits, 1, 4096, 10240);
check('what is left after one server', $left === ['servers' => 1, 'ram' => 4, 'disk' => 30720, 'cpu' => 200, 'backups' => 2]);
check('never below zero', Access::left($limits, 3, 20480, 99999)['servers'] === 0 && Access::left($limits, 3, 20480, 99999)['ram'] === 0);
check('a part of a GB in use rounds down', Access::left($limits, 0, 1500, 0)['ram'] === 6);

$server = ['memory' => 4096, 'disk' => 10240, 'cpu' => 100, 'backup_limit' => 2];
check('a server that fits passes', failsOn(fn () => Access::check($server, $left)) === null);
check('no servers left', failsOn(fn () => Access::check($server, ['servers' => 0] + $left)) === 'data.name');
check('too much RAM', failsOn(fn () => Access::check(['memory' => 6144] + $server, $left)) === 'data.ram');
check('too much disk', failsOn(fn () => Access::check(['disk' => 40960] + $server, $left)) === 'data.disk');
check('too much CPU', failsOn(fn () => Access::check(['cpu' => 300] + $server, $left)) === 'data.cpu');
check('too many backups', failsOn(fn () => Access::check(['backup_limit' => 5] + $server, $left)) === 'data.backups');

echo "PASS access\n";
