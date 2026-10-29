<?php

// Loads the plugin inside a real Pelican panel and checks what unit tests cannot: the panel loads the code, the
// translations and the pages. Run from the panel folder after `php artisan p:plugin:install simplify-server-maker`:
// php plugins/simplify-server-maker/tests/panel/smoke.php

use App\Enums\EggFormat;
use App\Models\Allocation;
use App\Models\Egg;
use App\Models\Node;
use App\Models\Role;
use App\Models\Server;
use App\Models\User;
use App\Services\Eggs\Sharing\EggImporterService;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Foundation\Testing\Concerns\InteractsWithAuthentication;
use Illuminate\Foundation\Testing\Concerns\InteractsWithSession;
use Illuminate\Foundation\Testing\Concerns\MakesHttpRequests;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;
use Simplify\Core\Addons\Addon;
use Simplify\ServerMaker\Creation\Ports;
use Simplify\ServerMaker\Filament\Admin\Pages\MakerSettings;
use Simplify\ServerMaker\Filament\Admin\Pages\ServerMaker;
use Simplify\ServerMaker\Filament\App\Pages\ServerMaker as AppServerMaker;
use Simplify\ServerMaker\Games\BundledEggs;
use Simplify\ServerMaker\Games\OtherGames;
use Simplify\ServerMaker\Models\Draft;
use Simplify\ServerMaker\Modpacks\Remote;
use Simplify\ServerMaker\ServerMakerAddon;
use Simplify\ServerMaker\Settings;
use Simplify\Subdomains\Subdomains;

define('LARAVEL_START', microtime(true));
require getcwd().'/vendor/autoload.php';

// Each step gets a fresh application, like a real request: Filament keeps panel state for the whole process, so an
// admin page and then an app page in one application can fail where a browser would not.
function boot(): void
{
    $app = require getcwd().'/bootstrap/app.php';
    $app->make(Kernel::class)->bootstrap();
    // Anything that fails outside a step (for example making the test user) must fail the job too: Laravel's own
    // handler prints the error and still exits with 0.
    set_exception_handler(function (Throwable $e): void {
        echo 'FAIL setup: '.get_class($e).': '.substr($e->getMessage(), 0, 400)."\n";
        exit(1);
    });
}
boot();

$failed = false;

function step(string $what, callable $run): void
{
    global $failed;
    try {
        boot();
        $run();
        echo "PASS $what\n";
    } catch (Throwable $e) {
        $failed = true;
        echo "FAIL $what: ".get_class($e).': '.substr($e->getMessage(), 0, 400)
            .' ('.basename($e->getFile()).':'.$e->getLine().")\n";
    }
}

function http(User $user, string $url): TestResponse
{
    $client = new class(app())
    {
        use InteractsWithAuthentication;
        use InteractsWithSession;
        use MakesHttpRequests;

        public function __construct(public $app) {}
    };

    return $client->actingAs($user)->get($url);
}

function expectOk(TestResponse $response, string $url): void
{
    if ($response->getStatusCode() !== 200) {
        $detail = $response->exception ? ': '.$response->exception->getMessage() : '';
        throw new RuntimeException('HTTP '.$response->getStatusCode().' at '.$url.$detail);
    }
}

$admin = User::where('email', 'smoke-maker@example.com')->first()
    ?? User::factory()->create(['email' => 'smoke-maker@example.com']);
$admin->syncRoles(Role::getRootAdmin());

step('the translations load', function () {
    if (__('simplify-server-maker::maker.addon.name') !== 'Server Maker') {
        throw new RuntimeException('got '.__('simplify-server-maker::maker.addon.name'));
    }
});
step('the add-on is tagged for Simplify', function () {
    $tagged = (fn () => $this->tags['simplify.addons'] ?? [])->call(app());
    if (! in_array(ServerMakerAddon::class, $tagged, true)) {
        throw new RuntimeException('not in the simplify.addons tag');
    }
});

step('the Server Maker page renders', function () use ($admin) {
    $url = ServerMaker::getUrl(panel: 'admin');
    expectOk(http($admin, $url), $url);
});

// A node with free ports, so the wizard can run to the end. Wings is not there, so Create stops at Wings.
$node = Node::query()->where('name', 'smoke-maker')->first()
    ?? Node::factory()->create(['name' => 'smoke-maker', 'memory' => 0, 'disk' => 0, 'cpu' => 0]);
foreach ([25565, 25566] as $port) {
    Allocation::query()->firstOrCreate(['node_id' => $node->id, 'ip' => '127.0.0.1', 'port' => $port]);
}

step('a Minecraft server goes through every step and imports its egg', function () use ($admin, $node) {
    Filament\Facades\Filament::setCurrentPanel(Filament\Facades\Filament::getPanel('admin'));
    $test = Livewire\Livewire::actingAs($admin)->test(ServerMaker::class)
        ->call('pickGame', 'minecraft', 'plugins')
        ->assertSet('data.software', 'paper')
        ->set('data.version', '1.21.1')
        ->call('prepareEgg')
        ->assertHasNoErrors();
    $egg = Egg::query()->find($test->get('data.egg_id'));
    if (! $egg || $egg->name !== 'Paper') {
        throw new RuntimeException('the Paper egg was not imported');
    }
    $test->set('data.name', 'smoke-oak')->set('data.node_id', $node->id)
        ->set('data.allocation_id', Ports::first($node->id))
        ->call('create');
    $server = Server::query()->where('name', 'smoke-oak')->first();
    // Without Wings Pelican removes the server again after the daemon call fails; reaching that call is enough.
    $errors = $test->errors()->toArray();
    if ($errors !== []) {
        throw new RuntimeException('validation: '.json_encode($errors));
    }
    $server?->delete();
});

step('the egg is imported only once', function () {
    if (Egg::query()->whereRaw('LOWER(name) = ?', ['paper'])->count() !== 1) {
        throw new RuntimeException('Paper is on the panel more than once');
    }
});

step('another game shows its own settings', function () use ($admin) {
    auth()->setUser($admin);
    $egg = Egg::query()->where('name', 'Smoke Valheim')->first();
    if (! $egg) {
        $definition = json_decode((string) file_get_contents(BundledEggs::directory().'/egg-vanilla-bedrock.json'), true);
        $definition['name'] = 'Smoke Valheim';
        unset($definition['uuid']);
        $egg = app(EggImporterService::class)
            ->fromContent(json_encode($definition), EggFormat::JSON, new Egg);
    }
    $cards = OtherGames::cards();
    if (! in_array($egg->id, array_column($cards, 'id'), true)) {
        throw new RuntimeException('the game is not offered');
    }
    Livewire\Livewire::actingAs($admin)->test(ServerMaker::class)
        ->call('pickGame', 'other', (string) $egg->id)
        ->assertSet('data.game', 'other')
        ->assertSet('data.egg_id', $egg->id)
        ->assertSee('Smoke Valheim');
});

step('a draft is saved after a step and opened again', function () use ($admin) {
    Draft::query()->where('user_id', $admin->id)->delete();
    Livewire\Livewire::actingAs($admin)->test(ServerMaker::class)
        ->call('pickGame', 'minecraft', 'mods')
        ->set('data.name', 'smoke-draft')
        ->call('stepDone', 1);
    $draft = Draft::for($admin->id);
    if (! $draft || $draft->step !== 2 || ($draft->data['type'] ?? null) !== 'mods') {
        throw new RuntimeException('no draft: '.json_encode($draft?->toArray()));
    }
    Livewire\Livewire::actingAs($admin)->test(ServerMaker::class)
        ->assertSet('data.name', 'smoke-draft')
        ->assertSet('step', 2)
        ->assertSet('restored', true);
    $draft->delete();
});

step('a Modrinth modpack is found and gets its egg', function () use ($admin) {
    // A fake Modrinth: one pack with one version for Minecraft 1.20.1.
    Remote::$transport = fn (string $url) => match (true) {
        str_ends_with($url, '/search') => ['hits' => [['project_id' => 'SMOKE1', 'slug' => 'smoke', 'title' => 'Smoke Pack', 'description' => 'A pack']]],
        str_ends_with($url, '/project/SMOKE1') => ['project_type' => 'modpack', 'slug' => 'smoke', 'title' => 'Smoke Pack'],
        str_ends_with($url, '/project/SMOKE1/version') => [['id' => 'SV1', 'version_number' => '1.0']],
        str_ends_with($url, '/version/SV1') => ['project_id' => 'SMOKE1', 'game_versions' => ['1.20.1']],
        default => [],
    };
    $test = Livewire\Livewire::actingAs($admin)->test(ServerMaker::class)
        ->call('pickGame', 'minecraft', 'modpack')
        ->set('data.modpack_search', 'smoke')
        ->assertSee('Smoke Pack')
        ->set('data.modpack', 'modrinth:SMOKE1')
        ->set('data.modpack_version', 'SV1')
        ->call('prepareEgg')
        ->assertHasNoErrors();
    $egg = Egg::query()->find($test->get('data.egg_id'));
    if ($egg?->name !== 'Modrinth Generic') {
        throw new RuntimeException('the Modrinth egg was not imported');
    }
    if ($test->instance()->java() !== 17) {
        throw new RuntimeException('expected Java 17 for 1.20.1, got '.var_export($test->instance()->java(), true));
    }
    Remote::$transport = null;
});

step('the settings page renders', function () use ($admin) {
    $url = MakerSettings::getUrl(panel: 'admin');
    expectOk(http($admin, $url), $url);
});

step('settings are saved and the CurseForge key stays encrypted', function () use ($admin, $node) {
    Filament\Facades\Filament::setCurrentPanel(Filament\Facades\Filament::getPanel('admin'));
    Livewire\Livewire::actingAs($admin)->test(MakerSettings::class)
        ->set('data.self_service', true)
        ->set('data.node_ids', [$node->id])
        ->set('data.minecraft', true)
        ->set('data.egg_ids', [])
        ->set('data.max_servers', 2)
        ->set('data.max_ram', 8)
        ->set('data.curseforge_key', 'smoke-secret-key')
        ->call('save')
        ->assertHasNoErrors()
        ->assertSet('data.curseforge_key', '');
    if (Settings::get('curseforge_key') !== 'smoke-secret-key' || ! Settings::get('self_service')) {
        throw new RuntimeException('not saved');
    }
    $raw = (string) DB::table('simplify_server_maker_settings')->where('key', 'curseforge_key')->value('value');
    if ($raw === '' || str_contains($raw, 'smoke-secret-key')) {
        throw new RuntimeException('the key is stored readable');
    }
});

$user = User::where('email', 'smoke-maker-user@example.com')->first()
    ?? User::factory()->create(['email' => 'smoke-maker-user@example.com']);
$user->syncRoles([]);

step('a user makes a server within their allowance, in the normal panel', function () use ($user, $node) {
    $url = AppServerMaker::getUrl(panel: 'app');
    expectOk(http($user, $url), $url);
    Filament\Facades\Filament::setCurrentPanel(Filament\Facades\Filament::getPanel('app'));
    auth()->setUser($user);
    if (Ports::nodes()->pluck('id')->all() !== [$node->id]) {
        throw new RuntimeException('the user sees other nodes');
    }
    if (OtherGames::cards() !== []) {
        throw new RuntimeException('the user is offered games that are not allowed');
    }
    $test = Livewire\Livewire::actingAs($user)->test(AppServerMaker::class)
        ->call('pickGame', 'minecraft', 'plugins')
        ->set('data.version', '1.21.1')
        ->call('prepareEgg')
        ->assertHasNoErrors()
        ->set('data.name', 'smoke-user')
        ->set('data.node_id', $node->id)
        ->set('data.allocation_id', Ports::first($node->id))
        ->set('data.ram', 'custom')
        ->set('data.ram_custom', 16)
        ->call('create')
        ->assertHasErrors(['data.ram_custom']);
    $test->set('data.ram', '4')->call('create')->assertHasNoErrors();
    Server::query()->where('name', 'smoke-user')->delete();
});

step('a user without servers left is stopped', function () use ($user, $node) {
    Settings::put(['max_servers' => 0]);
    Filament\Facades\Filament::setCurrentPanel(Filament\Facades\Filament::getPanel('app'));
    Livewire\Livewire::actingAs($user)->test(AppServerMaker::class)
        ->call('pickGame', 'minecraft', 'plugins')
        ->set('data.version', '1.21.1')
        ->call('prepareEgg')
        ->set('data.name', 'smoke-user')
        ->set('data.node_id', $node->id)
        ->set('data.allocation_id', Ports::first($node->id))
        ->set('data.ram', '4')
        ->call('create')
        ->assertHasErrors(['data.name']);
});

step('without self-service a user cannot open the Server Maker', function () use ($user) {
    Settings::put(['self_service' => false, 'max_servers' => null, 'curseforge_key' => null]);
    $url = AppServerMaker::getUrl(panel: 'app');
    $status = http($user, $url)->getStatusCode();
    if ($status !== 403) {
        throw new RuntimeException('HTTP '.$status);
    }
});

step('Simplify shows a Create server button to those who may use it', function () use ($admin, $user) {
    if (! class_exists(Addon::class)) {
        require dirname(__DIR__).'/stubs/simplify.php';
    }
    auth()->setUser($admin);
    $addon = new ServerMakerAddon;
    $buttons = $addon->dashboardActions();
    if (count($buttons) !== 1 || ! str_ends_with($buttons[0]->url, '/server-maker') || $addon->settingsUrl() === null) {
        throw new RuntimeException('admin: '.json_encode($buttons));
    }
    auth()->setUser($user);
    if ($addon->dashboardActions() !== [] || $addon->settingsUrl() !== null) {
        throw new RuntimeException('a user without self-service gets the button');
    }
});

step("Simplify's setup asks who may make servers and saves it", function () use ($admin, $node) {
    auth()->setUser($admin);
    $addon = new ServerMakerAddon;
    if (count($addon->setupFields()) !== 2 || ! array_key_exists('self_service', $addon->setupState())) {
        throw new RuntimeException('no setup fields');
    }
    $addon->saveSetup([...$addon->setupState(), 'self_service' => true, 'node_ids' => [(string) $node->id], 'max_servers' => '3']);
    if (! Settings::get('self_service') || Settings::get('node_ids') !== [$node->id] || Settings::get('max_servers') !== 3) {
        throw new RuntimeException('not saved: '.json_encode($addon->setupState()));
    }
    Settings::put(['self_service' => false, 'node_ids' => null, 'max_servers' => null]);
});

require dirname(__DIR__).'/stubs/subdomains.php';
$forwarded = Allocation::query()->where('node_id', $node->id)->where('port', 25566)->value('id');
Subdomains::$allocation = (int) $forwarded;

step('with Simplify Subdomains a server gets a subdomain on a forwarded port', function () use ($admin, $node, $forwarded) {
    Filament\Facades\Filament::setCurrentPanel(Filament\Facades\Filament::getPanel('admin'));
    Livewire\Livewire::actingAs($admin)->test(ServerMaker::class)
        ->assertSet('data.allocation_id', (int) $forwarded)
        ->call('pickGame', 'minecraft', 'plugins')
        ->set('data.version', '1.21.1')
        ->call('prepareEgg')
        ->set('data.name', 'Oak Survival')
        ->assertSet('data.subdomain', 'oak-survival')
        ->set('data.subdomain', 'taken')
        ->set('data.node_id', $node->id)
        ->call('create')
        ->assertHasErrors(['data.subdomain']);
});

if (! $failed) {
    echo "All steps passed\n";
}
exit($failed ? 1 : 0);
