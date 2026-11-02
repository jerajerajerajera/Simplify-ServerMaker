<?php

// Extra ports: which free ports of the node a server gets.

declare(strict_types=1);

require __DIR__.'/../bootstrap.php';

use Simplify\ServerMaker\Creation\ExtraPorts;

$free = [11 => 25570, 12 => 25566, 13 => 25560, 14 => 25567, 15 => '25564'];
check('the ports right after the main port first', ExtraPorts::pick(25565, $free, 2) === [12, 14]);
check('then the nearest ones below it', ExtraPorts::pick(25565, $free, 4) === [12, 14, 11, 15]);
check('never more than asked', count(ExtraPorts::pick(25565, $free, 1)) === 1);
check('nothing asked, nothing picked', ExtraPorts::pick(25565, $free, 0) === [] && ExtraPorts::pick(25565, $free, -2) === []);
check('too few free ports: all of them', count(ExtraPorts::pick(25565, $free, 9)) === 5);
check('no free ports', ExtraPorts::pick(25565, [], 2) === []);
check('at most ten extra ports', ExtraPorts::MAX === 10);

echo "PASS ports\n";
