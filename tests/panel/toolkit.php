<?php

// With Simplify Toolkit installed (run after smoke.php, from the panel folder): the wizard uses Toolkit's MOTD maker
// and offers an own automatic backup schedule.

declare(strict_types=1);

use App\Models\Role;
use App\Models\User;
use Illuminate\Contracts\Console\Kernel;
use Simplify\ServerMaker\Creation\BackupSchedule;
use Simplify\ServerMaker\Filament\Admin\Pages\ServerMaker;

define('LARAVEL_START', microtime(true));
require getcwd().'/vendor/autoload.php';
$app = require getcwd().'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
Filament\Facades\Filament::setCurrentPanel(Filament\Facades\Filament::getPanel('admin'));

$admin = User::where('email', 'smoke-maker@example.com')->firstOrFail();
$admin->syncRoles(Role::getRootAdmin());
$failed = false;

function step(string $what, callable $run): void
{
    global $failed;
    try {
        $run();
        echo "PASS $what\n";
    } catch (Throwable $e) {
        $failed = true;
        echo "FAIL $what: ".get_class($e).': '.substr($e->getMessage(), 0, 400).' ('.basename($e->getFile()).':'.$e->getLine().")\n";
    }
}

step('the MOTD uses Toolkit\'s MOTD maker', function () use ($admin) {
    Livewire\Livewire::actingAs($admin)->test(ServerMaker::class)
        ->call('pickGame', 'minecraft', 'plugins')
        ->assertSeeHtml('simplifyMotdEditor');
});

step('the Resources step offers an own backup schedule with the panel\'s values', function () use ($admin) {
    if (! BackupSchedule::available()) {
        throw new RuntimeException('Toolkit\'s automatic backups are not seen');
    }
    $panel = Simplify\Toolkit\Services\AutomaticBackups::panel();
    Livewire\Livewire::actingAs($admin)->test(ServerMaker::class)
        ->assertSet('data.backup_schedule', false)
        ->assertSet('data.backup_every', (int) $panel->every_minutes)
        ->assertSet('data.backup_keep', (int) $panel->keep);
});

if (! $failed) {
    echo "All steps passed\n";
}
exit($failed ? 1 : 0);
