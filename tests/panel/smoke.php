<?php

// Loads the plugin inside a real Pelican panel and checks what unit tests cannot: the panel loads the code, the
// translations and the pages. Run from the panel folder after `php artisan p:plugin:install simplify-servermaker`:
// php plugins/simplify-servermaker/tests/panel/smoke.php

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
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Simplify\Core\Addons\Addon;
use Simplify\ServerMaker\Creation\ApplyFirstSettings;
use Simplify\ServerMaker\Creation\ApplySteamArt;
use Simplify\ServerMaker\Creation\Ports;
use Simplify\ServerMaker\Filament\Admin\Pages\MakerSettings;
use Simplify\ServerMaker\Filament\Admin\Pages\ServerMaker;
use Simplify\ServerMaker\Filament\App\Pages\ServerMaker as AppServerMaker;
use Simplify\ServerMaker\Games\BundledEggs;
use Simplify\ServerMaker\Games\EggIndex;
use Simplify\ServerMaker\Games\OtherGames;
use Simplify\ServerMaker\Games\SteamArt;
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
    if (__('simplify-servermaker::maker.addon.name') !== 'Server Maker') {
        throw new RuntimeException('got '.__('simplify-servermaker::maker.addon.name'));
    }
});
step('the add-on is tagged for Simplify', function () {
    $tagged = (fn () => $this->tags['simplify.addons'] ?? [])->call(app());
    if (! in_array(ServerMakerAddon::class, $tagged, true)) {
        throw new RuntimeException('not in the simplify.addons tag');
    }
});

step('the Server Maker page renders, with Simplify buttons instead of bare Filament buttons', function () use ($admin) {
    $url = ServerMaker::getUrl(panel: 'admin');
    $response = http($admin, $url);
    expectOk($response, $url);
    // The core's screen audit fails any .fi-btn inside host mode's frame.
    if (preg_match('/<button[^>]*class="[^"]*\\bfi-btn\\b[^"]*"/', $response->getContent(), $bare)) {
        throw new RuntimeException('bare Filament button: '.$bare[0]);
    }
    if (! str_contains($response->getContent(), 'smk-next')) {
        throw new RuntimeException('no Next button');
    }
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
        ->assertSet('data.max_players', 20)
        ->assertSet('data.eula', false)
        ->assertSee('Game mode')
        // Java gets the rest of the 0.7.0 server properties on the same step.
        ->assertSet('data.motd', 'A Minecraft Server')
        ->assertSet('data.view_distance', 10)
        ->assertSee(['View distance (chunks)', 'Spawn protection (blocks)', 'PvP', 'Whitelist', 'Allow flight']);
    // The EULA box is never ticked for the user, and never blocks the browser from sending the form.
    if (! preg_match('/<input[^>]*data\.eula[^>]*>/', $test->html(), $box) || str_contains($box[0], 'required') || str_contains($box[0], 'checked')) {
        throw new RuntimeException('the EULA box is missing, required or ticked: '.($box[0] ?? ''));
    }
    $test->set('data.eula', true);
    // Players, game mode and difficulty are checked before the server is made.
    $test->set('data.max_players', 5000)->call('create');
    if (! array_key_exists('data.max_players', $test->errors()->toArray()) || Server::query()->where('name', 'smoke-oak')->exists()) {
        throw new RuntimeException('too many players was accepted');
    }
    $test->set('data.max_players', 12)->set('data.view_distance', 99)->call('create');
    if (! array_key_exists('data.view_distance', $test->errors()->toArray()) || Server::query()->where('name', 'smoke-oak')->exists()) {
        throw new RuntimeException('a view distance of 99 was accepted');
    }
    $test->set('data.view_distance', 8)->set('data.motd', '§aSmoke oak')->set('data.white_list', true)
        ->set('data.gamemode', 'creative')->call('create');
    $server = Server::query()->where('name', 'smoke-oak')->first();
    // Without Wings Pelican removes the server again after the daemon call fails; reaching that call is enough.
    $errors = $test->errors()->toArray();
    if ($errors !== []) {
        throw new RuntimeException('validation: '.json_encode($errors));
    }
    $server?->delete();
});

step('the first settings are queued for after the installation', function () {
    // Pelican runs a real queue; the dispatcher must accept the job (1.3.0 broke here with a method named queue()).
    $before = config('queue.default');
    config(['queue.default' => 'database']);
    try {
        $server = new Server;
        $server->id = 987654;
        ApplyFirstSettings::afterInstall($server, ['properties' => ['max-players' => '8']]);
        $jobs = DB::table('jobs')->where('payload', 'like', '%ApplyFirstSettings%');
        if (! $jobs->exists()) {
            throw new RuntimeException('the job was not queued');
        }
        $jobs->delete();
    } finally {
        config(['queue.default' => $before]);
    }
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
    if (! in_array('egg:'.$egg->id, array_column($cards, 'key'), true)) {
        throw new RuntimeException('the game is not offered');
    }
    Livewire\Livewire::actingAs($admin)->test(ServerMaker::class)
        ->call('pickGame', 'other', 'egg:'.$egg->id)
        ->assertSet('data.game', 'other')
        ->assertSet('data.egg_id', $egg->id)
        ->assertSee('Smoke Valheim');
});

step('another game offers its Steam banner and icon, copied once after creation', function () use ($admin) {
    // The store search for the smoke egg answers Valheim; its images come from Steam's real CDN.
    $art = SteamArt::pick([['id' => 892970, 'name' => 'Smoke Valheim']], 'Smoke Valheim');
    Cache::put('simplify-servermaker.steam.'.md5('smoke valheim'), $art, 60);
    $egg = Egg::query()->where('name', 'Smoke Valheim')->firstOrFail();
    Livewire\Livewire::actingAs($admin)->test(ServerMaker::class)
        ->call('pickGame', 'other', 'egg:'.$egg->id)
        ->assertSet('data.steam_art', true)
        ->assertSee(['Use the Steam banner and icon', 'Found on Steam: Smoke Valheim']);
    Settings::put(['steam_art' => false]);
    if (SteamArt::find('Smoke Valheim Dedicated Server') !== null) {
        throw new RuntimeException('the setting Offer Steam banners and icons is ignored');
    }
    Settings::put(['steam_art' => null]);
    $server = new Server;
    $server->uuid = (string) Str::uuid();
    ApplySteamArt::apply($server, $art);
    if (method_exists($server, 'writeIcon')) {
        $path = Server::getIconStoragePath().'/'.$server->uuid.'.png';
        if (! Storage::disk('public')->exists($path) || getimagesizefromstring(Storage::disk('public')->get($path))[0] !== 128) {
            throw new RuntimeException('the Steam icon is not the server icon');
        }
        Storage::disk('public')->delete($path);
    }
});

step('a game from the egg index is offered, checked and added on Next', function () use ($admin) {
    auth()->setUser($admin);
    $game = collect(EggIndex::games())->first(fn (array $game) => filled($game['uuid'] ?? null) && filled($game['sha256'] ?? null) && ! Egg::query()->where('uuid', $game['uuid'])->exists()
        && ! Egg::query()->where('name', $game['name'])->exists());
    if (! $game) {
        throw new RuntimeException('the egg index is empty');
    }
    if (! in_array('index:'.$game['id'], array_column(OtherGames::cards(), 'key'), true)) {
        throw new RuntimeException('the index game is not offered');
    }
    // A download that does not match the checksum is never imported.
    if (EggIndex::matches($game, '{"name":"tampered"}')) {
        throw new RuntimeException('a download with the wrong checksum is accepted');
    }
    $test = Livewire\Livewire::actingAs($admin)->test(ServerMaker::class)
        ->call('pickGame', 'other', 'index:'.$game['id'])
        ->assertSet('data.game_index', $game['id'])
        ->assertSet('data.egg_id', null);
    // The egg arriving meanwhile (same uuid) is used as it is.
    $definition = json_decode((string) file_get_contents(BundledEggs::directory().'/egg-vanilla-bedrock.json'), true);
    $definition['name'] = 'Smoke '.$game['name'];
    $definition['uuid'] = $game['uuid'];
    $egg = app(EggImporterService::class)->fromContent(json_encode($definition), EggFormat::JSON, new Egg);
    $test->call('gameDone')
        ->assertHasNoErrors()
        ->assertSet('data.egg_id', $egg->id)
        ->assertSet('data.game_index', null);
    $egg->delete();
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
        ->assertSet('restored', true)
        ->assertSet('hasDraft', true)
        // The 0.7.0 bar at the bottom: the steps (Minecraft as step 2) and the draft with Discard.
        ->assertSee('smk-navbar', false)
        ->assertSeeInOrder(['Game', 'Minecraft', 'Server', 'Resources', 'Review'])
        ->assertSee('Draft saved')
        ->call('discardDraft')
        ->assertRedirect();
    if (Draft::for($admin->id)) {
        throw new RuntimeException('Discard kept the draft');
    }
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

step('the settings page renders and fits the window', function () use ($admin) {
    $url = MakerSettings::getUrl(panel: 'admin');
    $response = http($admin, $url);
    expectOk($response, $url);
    // With the core, s-fit makes only the form scroll, never the page.
    if (! str_contains($response->getContent(), 'class="s-fit"')) {
        throw new RuntimeException('the settings form has no s-fit');
    }
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
        ->set('data.eula', true)
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
        ->set('data.eula', true)
        ->call('create')
        ->assertHasErrors(['data.subdomain']);
});

if (! $failed) {
    echo "All steps passed\n";
}
exit($failed ? 1 : 0);
