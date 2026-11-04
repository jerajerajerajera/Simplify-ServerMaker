<?php

// With Simplify Toolkit installed (run after smoke.php, from the panel folder): the wizard uses Toolkit's MOTD maker
// and offers an own automatic backup schedule.

use App\Models\Egg;
use App\Models\Role;
use App\Models\Server;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Simplify\ServerMaker\Creation\BackupSchedule;
use Simplify\ServerMaker\Filament\Admin\Pages\ServerMaker;
use Simplify\ServerMaker\Filament\Server\Pages\Performance;
use Simplify\ServerMaker\Filament\Server\Pages\ServerSoftware;
use Simplify\ServerMaker\ServerSettings\ToolkitSections;
use Simplify\ServerMaker\Software\Startup;
use Simplify\Toolkit\Filament\Server\Pages\ServerSettings;
use Simplify\Toolkit\Services\AutomaticBackups;
use Simplify\Toolkit\Services\SettingsSections;

define('LARAVEL_START', microtime(true));
require getcwd().'/vendor/autoload.php';
$app = require getcwd().'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
Filament::setCurrentPanel(Filament::getPanel('admin'));

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
    Livewire::actingAs($admin)->test(ServerMaker::class)
        ->call('pickGame', 'minecraft', 'plugins')
        ->assertSeeHtml('simplifyMotdEditor');
});

step('the Resources step offers an own backup schedule with the panel\'s values', function () use ($admin) {
    if (! BackupSchedule::available()) {
        throw new RuntimeException('Toolkit\'s automatic backups are not seen');
    }
    $panel = AutomaticBackups::panel();
    Livewire::actingAs($admin)->test(ServerMaker::class)
        ->assertSet('data.backup_schedule', false)
        ->assertSet('data.backup_every', (int) $panel->every_minutes)
        ->assertSet('data.backup_keep', (int) $panel->keep);
});

step('Server software and Performance are sections of Toolkit\'s server settings, with its one Save, not pages of their own', function () use ($admin) {
    $ours = array_filter(iterator_to_array(app()->tagged(SettingsSections::TAG)), fn ($sections) => $sections instanceof ToolkitSections);
    if (count($ours) !== 1) {
        throw new RuntimeException('Toolkit does not see the Server Maker\'s sections');
    }
    $sections = reset($ours);
    $paper = Egg::query()->where('name', 'Paper')->firstOrFail();
    $server = Server::factory()->create(['owner_id' => $admin->id, 'egg_id' => $paper->id, 'name' => 'smoke-settings', 'installed_at' => now(), 'memory' => 4096, 'cpu' => 100, 'disk' => 10240]);
    Startup::write($server, ['egg_id' => $paper->id, 'image' => (string) $server->image, 'startup' => (string) $server->startup, 'environment' => ['MINECRAFT_VERSION' => '1.21.1']]);
    Filament::setCurrentPanel(Filament::getPanel('server'));
    Filament::setTenant($server);
    auth()->setUser($admin);
    if (ServerSoftware::canAccess() || Performance::canAccess()) {
        throw new RuntimeException('the Server software or Performance page still opens next to the sections');
    }
    $headings = array_map(fn ($section) => (string) $section->getHeading(), $sections->serverSettingsSections($server));
    if ($headings !== ['Server software', 'Performance']) {
        throw new RuntimeException('the sections are not Server software and Performance: '.json_encode($headings));
    }
    // On Toolkit's page, after its own sections: the head with what runs now, the limits.
    $html = Livewire::actingAs($admin)->test(ServerSettings::class)->html();
    foreach (['Server software', 'Now: Paper 1.21.1', 'Type smoke-settings to confirm', 'Performance', 'New limits apply after the next restart.'] as $text) {
        if (! str_contains($html, $text)) {
            throw new RuntimeException("Toolkit's server settings do not show: $text");
        }
    }
    $state = $sections->serverSettingsFill($server);
    if ([$state['type'], $state['software'], $state['version'], $state['ram']] !== ['plugins', 'paper', '1.21.1', '4']) {
        throw new RuntimeException('the sections do not start at the server: '.json_encode($state));
    }
    // New limits save; the software stays, because its name was not typed.
    if (! $sections->serverSettingsSave($server, [...$state, 'ram' => 'custom', 'ram_custom' => 6]) || (int) $server->fresh()->memory !== 6144) {
        throw new RuntimeException('Save did not apply the new RAM: '.$server->fresh()->memory);
    }
    // Other software with a wrong name: an error on the confirm field of this part of the page, nothing reinstalls.
    try {
        $sections->serverSettingsSave($server->fresh(), [...$sections->serverSettingsFill($server->fresh()), 'type' => 'vanilla', 'software' => null, 'confirm' => 'smoke']);
        throw new LogicException('a wrong name was accepted');
    } catch (ValidationException $exception) {
        if (! array_key_exists('data.sections.simplify-servermaker.confirm', $exception->errors())) {
            throw new RuntimeException('the error is not on the confirm field: '.json_encode($exception->errors()));
        }
    }
    $server->delete();
});

if (! $failed) {
    echo "All steps passed\n";
}
exit($failed ? 1 : 0);
