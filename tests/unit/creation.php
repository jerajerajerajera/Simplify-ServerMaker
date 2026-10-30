<?php

// Resources and the payload for Pelican's ServerCreationService.

declare(strict_types=1);

require __DIR__.'/../bootstrap.php';

use Simplify\ServerMaker\Creation\EggData;
use Simplify\ServerMaker\Creation\FirstSettings;
use Simplify\ServerMaker\Creation\Payload;
use Simplify\ServerMaker\Creation\Resources;

check('presets include the starting value', Resources::presets('ram', 6) === [2, 4, 6, 8, 12, 16]);
check('presets add an unusual start', in_array(3, Resources::presets('ram', 3), true));
check('presets stop at the limit', Resources::presets('cpu', 200, 150) === [100, 150]);
check('a start above the limit is cut to it', Resources::presets('ram', 8, 5) === [2, 4, 5]);
check('a preset stays a preset', Resources::choice(4, [2, 4]) === '4');
check('another amount is custom', Resources::choice(5, [2, 4]) === 'custom');
check('the custom amount counts', Resources::amount(['ram' => 'custom', 'ram_custom' => '10'], 'ram') === 10);
check('a broken amount is zero', Resources::amount(['ram' => 'lots'], 'ram') === 0);
check('the payload is in MB and %', Resources::payload(['ram' => '4', 'cpu' => '150', 'disk' => '10240']) === ['memory' => 4096, 'cpu' => 150, 'disk' => 10240]);
check('no RAM is refused', failsOn(fn () => Resources::payload(['ram' => 'custom', 'ram_custom' => 0, 'cpu' => '100', 'disk' => '10240'])) === 'data.ram');
check('a user over their limit is refused', failsOn(fn () => Resources::payload(['ram' => '8', 'cpu' => '100', 'disk' => '10240'], ['ram' => 4, 'cpu' => 200, 'disk' => 20480])) === 'data.ram');
check('a tiny disk is refused', failsOn(fn () => Resources::payload(['ram' => '4', 'cpu' => '100', 'disk' => 'custom', 'disk_custom' => 100])) === 'data.disk');
check('disk in GB reads well', Resources::gb(10240) === '10' && Resources::gb(1536) === '1.5');

$paper = new EggData(7, 'Paper', [
    'MINECRAFT_VERSION' => ['default' => 'latest', 'user' => true],
    'BUILD_NUMBER' => ['default' => 'latest', 'user' => true],
], ['Java 21' => 'ghcr.io/pelican-eggs/yolks:java_21', 'Java 17' => 'ghcr.io/pelican-eggs/yolks:java_17'], 'java -jar server.jar');
$resources = ['memory' => 4096, 'cpu' => 150, 'disk' => 10240];
$state = ['game' => 'minecraft', 'type' => 'plugins', 'software' => 'paper', 'version' => '1.20.4', 'name' => ' Oak ', 'backups' => 2];
$payload = Payload::build($state, $paper, $resources, 3, 1, 9, 17, false);
check('the name is trimmed', $payload['name'] === 'Oak');
check('the version is set', $payload['environment']['MINECRAFT_VERSION'] === '1.20.4');
check('the Java image is picked', $payload['image'] === 'ghcr.io/pelican-eggs/yolks:java_17');
check('owner, node, port and egg are set', [$payload['owner_id'], $payload['node_id'], $payload['allocation_id'], $payload['egg_id']] === [3, 1, 9, 7]);
check('the server does not start by itself', $payload['start_on_completion'] === false);
check('users cannot skip the install script', Payload::build([...$state, 'skip_scripts' => true], $paper, $resources, 3, 1, 9, 17, false)['skip_scripts'] === false);
check('users cannot change the image', Payload::build([...$state, 'image' => 'evil/image'], $paper, $resources, 3, 1, 9, 17, false)['image'] !== 'evil/image');
check('admins can change the image', Payload::build([...$state, 'image' => 'my/image'], $paper, $resources, 3, 1, 9, 17, true)['image'] === 'my/image');
check('admins can override a variable', Payload::build([...$state, 'environment' => ['BUILD_NUMBER' => '100']], $paper, $resources, 3, 1, 9, 17, true)['environment']['BUILD_NUMBER'] === '100');
check('an unknown override is refused', failsOn(fn () => Payload::build([...$state, 'environment' => ['NOPE' => '1']], $paper, $resources, 3, 1, 9, 17, true)) === 'data.environment');
check('an empty name is refused', failsOn(fn () => Payload::build([...$state, 'name' => '  '], $paper, $resources, 3, 1, 9, 17, false)) === 'data.name');
check('a broken version is refused', failsOn(fn () => Payload::build([...$state, 'version' => 'latest; rm'], $paper, $resources, 3, 1, 9, 17, false)) === 'data.version');

$valheim = new EggData(12, 'Valheim', [
    'SERVER_NAME' => ['default' => 'My server', 'user' => true],
    'SRCDS_APPID' => ['default' => '896660', 'user' => false],
], ['ghcr.io/parkervcp/steamcmd:debian' => 'ghcr.io/parkervcp/steamcmd:debian'], './valheim_server');
$game = ['game' => 'other', 'name' => 'Viking', 'variables' => ['SERVER_NAME' => 'Vikings']];
$payload = Payload::build($game, $valheim, $resources, 3, 1, 9, null, false);
check('a game variable is set', $payload['environment'] === ['SERVER_NAME' => 'Vikings', 'SRCDS_APPID' => '896660']);
check('another game uses its own image', $payload['image'] === 'ghcr.io/parkervcp/steamcmd:debian');
check('users cannot set a hidden variable', failsOn(fn () => Payload::build([...$game, 'variables' => ['SRCDS_APPID' => '1']], $valheim, $resources, 3, 1, 9, null, false)) === 'data.variables');
check('admins can set every variable', Payload::build([...$game, 'variables' => ['SRCDS_APPID' => '1']], $valheim, $resources, 3, 1, 9, null, true)['environment']['SRCDS_APPID'] === '1');
check('a toggle becomes 1 or 0', Payload::gameEnvironment(['SERVER_NAME' => true], $valheim, false)['SERVER_NAME'] === '1');
$first = FirstSettings::from(['game' => 'minecraft', 'type' => 'plugins', 'version' => '1.21.1', 'max_players' => '12', 'gamemode' => 'creative', 'difficulty' => 'hard', 'eula' => true]);
check('players, game mode and difficulty go to server.properties', $first === ['properties' => ['max-players' => '12', 'gamemode' => 'creative', 'difficulty' => 'hard'], 'eula' => true]);
check('the EULA is optional when the server is made', ! array_key_exists('eula', FirstSettings::from(['game' => 'minecraft', 'type' => 'plugins'])));
check('Bedrock has no EULA box', ! array_key_exists('eula', FirstSettings::from(['game' => 'minecraft', 'type' => 'bedrock'])));
check('old releases get numbers', FirstSettings::from(['game' => 'minecraft', 'type' => 'vanilla', 'version' => '1.12.2', 'gamemode' => 'creative', 'difficulty' => 'hard', 'eula' => true])['properties']['gamemode'] === '1');
check('a proxy and other games get nothing', FirstSettings::from(['game' => 'minecraft', 'type' => 'proxy']) === [] && FirstSettings::from(['game' => 'other']) === []);
check('too many players is refused', failsOn(fn () => FirstSettings::from(['game' => 'minecraft', 'type' => 'vanilla', 'max_players' => 5000])) === 'data.max_players');
check('a strange game mode is refused', failsOn(fn () => FirstSettings::from(['game' => 'minecraft', 'type' => 'vanilla', 'gamemode' => 'god'])) === 'data.gamemode');
$properties = "#Minecraft server properties\nmotd=Hello\nmax-players=20\ngamemode=survival\n";
check('only the chosen keys change', FirstSettings::merge($properties, ['max-players' => '8', 'difficulty' => 'hard']) === "#Minecraft server properties\nmotd=Hello\nmax-players=8\ngamemode=survival\ndifficulty=hard\n");
check('an empty file gets the keys', FirstSettings::merge('', ['max-players' => '8']) === "max-players=8\n");
echo "PASS creation\n";
