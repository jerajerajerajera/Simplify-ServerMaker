<?php

// Loads the plugin inside a real Pelican panel and checks what unit tests cannot: the panel loads the code, the
// translations and the pages. Run from the panel folder after `php artisan p:plugin:install simplify-servermaker`:
// php plugins/simplify-servermaker/tests/panel/smoke.php

use App\Enums\EggFormat;
use App\Enums\ServerState;
use App\Models\Allocation;
use App\Models\Egg;
use App\Models\Node;
use App\Models\Role;
use App\Models\Server;
use App\Models\User;
use App\Services\Eggs\Sharing\EggImporterService;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Foundation\Testing\Concerns\InteractsWithAuthentication;
use Illuminate\Foundation\Testing\Concerns\InteractsWithSession;
use Illuminate\Foundation\Testing\Concerns\MakesHttpRequests;
use Illuminate\Http\Client\Factory;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Illuminate\Validation\ValidationException;
use Simplify\Core\Addons\Addon;
use Simplify\Core\Support\PanelSettings;
use Simplify\ServerMaker\Access;
use Simplify\ServerMaker\CoreApi;
use Simplify\ServerMaker\Creation\ApplyFirstSettings;
use Simplify\ServerMaker\Creation\ApplySteamArt;
use Simplify\ServerMaker\Creation\ExtraPorts;
use Simplify\ServerMaker\Creation\Ports;
use Simplify\ServerMaker\Creation\Resources;
use Simplify\ServerMaker\Filament\Admin\Pages\MakerSettings;
use Simplify\ServerMaker\Filament\Admin\Pages\ServerMaker;
use Simplify\ServerMaker\Filament\App\Pages\ServerMaker as AppServerMaker;
use Simplify\ServerMaker\Filament\App\Pages\Steam;
use Simplify\ServerMaker\Filament\Server\Pages\Performance;
use Simplify\ServerMaker\Filament\Server\Pages\ServerSoftware;
use Simplify\ServerMaker\Games\BundledEggs;
use Simplify\ServerMaker\Games\EggIndex;
use Simplify\ServerMaker\Games\Minecraft;
use Simplify\ServerMaker\Games\MinecraftEggs;
use Simplify\ServerMaker\Games\OtherGames;
use Simplify\ServerMaker\Games\SteamArt;
use Simplify\ServerMaker\Http\Middleware\OpenServerMaker;
use Simplify\ServerMaker\Livewire\MakerSettingsForm;
use Simplify\ServerMaker\Livewire\SteamConnection;
use Simplify\ServerMaker\Models\Draft;
use Simplify\ServerMaker\Models\WaitingSettings;
use Simplify\ServerMaker\Modpacks\Remote;
use Simplify\ServerMaker\Problems\ProblemLog;
use Simplify\ServerMaker\Problems\ServerMakerProblems;
use Simplify\ServerMaker\ServerMakerAddon;
use Simplify\ServerMaker\ServerMakerPlugin;
use Simplify\ServerMaker\Settings;
use Simplify\ServerMaker\Software\ChangeSoftware;
use Simplify\ServerMaker\Software\Startup;
use Simplify\ServerMaker\Steam\SteamKeys;
use Simplify\Subdomains\Subdomains;
use Symfony\Component\HttpKernel\Exception\HttpException;

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

/** @param  array<string, mixed>  $session */
function http(User $user, string $url, array $session = []): TestResponse
{
    $client = new class(app())
    {
        use InteractsWithAuthentication;
        use InteractsWithSession;
        use MakesHttpRequests;

        public function __construct(public $app) {}
    };

    return $client->actingAs($user)->withSession($session)->get($url);
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
    // Without Simplify the settings open from this page; with Simplify from Simplify's settings.
    Filament\Facades\Filament::setCurrentPanel(Filament\Facades\Filament::getPanel('admin'));
    $page = Livewire\Livewire::actingAs($admin)->test(ServerMaker::class);
    class_exists(Addon::class) ? $page->assertActionHidden('settings') : $page->assertActionVisible('settings');
    // With Simplify 2.0 (add-on API 8) Create server opens it everywhere, so it leaves the admin menu; without, it stays.
    $users = http($admin, '/admin/users');
    expectOk($users, '/admin/users');
    $inMenu = (bool) preg_match('#href="[^"]*/admin/server-maker"#', $users->getContent());
    if ($inMenu === CoreApi::atLeast(8)) {
        throw new RuntimeException(CoreApi::atLeast(8) ? 'the admin menu still links the Server Maker' : 'the admin menu does not link the Server Maker');
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
    // Its help says what ticking does: the first start does not ask. The server never starts by itself (SM-B10).
    $test->assertSee('Tick it now and it will not ask again')->assertDontSee('starts straight away');
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

step('the first settings wait for the installation and Pelican\'s scheduler writes them, without a queue worker (SM-B7)', function () use ($admin, $node) {
    // The usual small panel: only the scheduler's cron runs, no queue worker (QUEUE_CONNECTION=sync).
    $before = config('queue.default');
    config(['queue.default' => 'sync']);
    $written = [];
    Http::fake(function (Request $request) use (&$written) {
        if (str_contains($request->url(), '/files/write')) {
            parse_str((string) parse_url($request->url(), PHP_URL_QUERY), $query);
            $written[$query['file'] ?? '?'] = $request->body();
        }

        return Http::response("max-players=20\nmotd=Smoke\n");
    });
    $server = Server::factory()->create(['owner_id' => $admin->id, 'node_id' => $node->id, 'name' => 'smoke-first-settings', 'status' => ServerState::Installing]);
    try {
        ApplyFirstSettings::afterInstall($server, ['properties' => ['max-players' => '8'], 'eula' => true]);
        Artisan::call('simplify-servermaker:first-settings');
        if ($written !== []) {
            throw new RuntimeException('written before the installation finished: '.implode(', ', array_keys($written)));
        }
        $server->forceFill(['installed_at' => now(), 'status' => null])->save();
        Artisan::call('simplify-servermaker:first-settings');
        if (! str_contains($written['server.properties'] ?? '', 'max-players=8') || ! str_contains($written['server.properties'], 'motd=Smoke') || ! str_contains($written['eula.txt'] ?? '', 'eula=true')) {
            throw new RuntimeException('not written after the installation: '.json_encode($written));
        }
        $written = [];
        Artisan::call('simplify-servermaker:first-settings');
        if ($written !== [] || WaitingSettings::query()->where('server_id', $server->id)->exists()) {
            throw new RuntimeException('the settings were written a second time');
        }
        // A job that 2.0.0-beta.10 or older left in the queue hands its settings to the scheduler.
        (new ApplyFirstSettings((int) $server->id, ['eula' => true]))->handle();
        if (WaitingSettings::query()->where('server_id', $server->id)->value('settings') === null) {
            throw new RuntimeException('an older queued job lost its settings');
        }
        // Wings does not answer: the settings stay for the next minute; after two hours they are dropped.
        Http::swap(new Factory);
        Http::fake(fn () => Http::response('', 500));
        Artisan::call('simplify-servermaker:first-settings');
        if (! WaitingSettings::query()->where('server_id', $server->id)->exists()) {
            throw new RuntimeException('the settings were dropped after one failed try');
        }
        WaitingSettings::query()->where('server_id', $server->id)->update(['created_at' => now()->subHours(3)]);
        Artisan::call('simplify-servermaker:first-settings');
        if (WaitingSettings::query()->where('server_id', $server->id)->exists()) {
            throw new RuntimeException('the settings still wait after two hours');
        }
        $scheduled = array_filter(app(Schedule::class)->events(), fn ($event) => str_contains((string) $event->command, 'simplify-servermaker:first-settings'));
        if (count($scheduled) !== 1) {
            throw new RuntimeException('the command is not in Pelican\'s schedule');
        }
    } finally {
        config(['queue.default' => $before]);
        WaitingSettings::query()->where('server_id', $server->id)->delete();
        $server->delete();
    }
});

step('the egg is imported only once', function () {
    if (Egg::query()->whereRaw('LOWER(name) = ?', ['paper'])->count() !== 1) {
        throw new RuntimeException('Paper is on the panel more than once');
    }
});

step('a required variable the egg leaves empty, as in the real Paper egg, does not stop or silently fail the Server Maker', function () use ($admin, $node) {
    // Pelican's own Paper egg (pelican-eggs/minecraft) has a User Agent: required, no default, hidden from users. The
    // bundled egg does not, so the panel's egg gets one here, as after an update with Egg Updates.
    $paper = Egg::query()->where('name', 'Paper')->firstOrFail();
    $variable = fn (string $env) => $paper->variables()->create(['name' => Str::headline(strtolower($env)), 'description' => 'Smoke', 'env_variable' => $env, 'default_value' => '', 'user_viewable' => false, 'user_editable' => false, 'rules' => ['required', 'string'], 'sort' => 9]);
    $agent = $variable('USER_AGENT');
    $make = function (string $name) use ($admin, $node) {
        Filament\Facades\Filament::setCurrentPanel(Filament\Facades\Filament::getPanel('admin'));
        session()->forget('filament.notifications');

        return Livewire\Livewire::actingAs($admin)->test(ServerMaker::class)
            ->call('pickGame', 'minecraft', 'plugins')->set('data.version', '1.21.1')->call('prepareEgg')
            ->set('data.name', $name)->set('data.node_id', $node->id)->set('data.allocation_id', Ports::first($node->id))
            ->call('create');
    };
    try {
        // Without Wings Pelican removes the server again after the daemon call fails; passing its checks is enough.
        $test = $make('smoke-agent');
        if ($test->errors()->isNotEmpty()) {
            throw new RuntimeException('the empty User Agent stopped the server: '.json_encode($test->errors()->toArray()));
        }
        // Another required variable without a value cannot be guessed: Pelican's message shows, not nothing at all.
        $secret = $variable('SMOKE_SECRET');
        try {
            $make('smoke-secret')->assertNotified(__('simplify-servermaker::maker.review.failed'));
        } finally {
            $secret->delete();
        }
        if (Server::query()->where('name', 'smoke-secret')->exists()) {
            throw new RuntimeException('a server was made without its required variable');
        }
    } finally {
        Server::query()->whereIn('name', ['smoke-agent', 'smoke-secret'])->get()->each->delete();
        $agent->delete();
    }
});

step('Server software is offered for Minecraft Java, checked before it reinstalls, and undone when the reinstall fails', function () use ($admin) {
    $paper = Egg::query()->where('name', 'Paper')->firstOrFail();
    $server = Server::factory()->create(['owner_id' => $admin->id, 'egg_id' => $paper->id, 'name' => 'smoke-software', 'installed_at' => now()]);
    Startup::write($server, ['egg_id' => $paper->id, 'image' => (string) $server->image, 'startup' => (string) $server->startup, 'environment' => ['MINECRAFT_VERSION' => '1.21.1']]);
    $before = Startup::of($server->fresh());

    expectOk(http($admin, ServerSoftware::getUrl(panel: 'server', tenant: $server)), 'Server software');
    Filament\Facades\Filament::setCurrentPanel(Filament\Facades\Filament::getPanel('server'));
    Filament\Facades\Filament::setTenant($server);
    // Plain checks instead of Livewire's assertions: a failing PHPUnit assertion cannot report outside PHPUnit.
    $test = Livewire\Livewire::actingAs($admin)->test(ServerSoftware::class);
    $html = $test->html();
    foreach (['Server software', 'Now: Paper 1.21.1', 'Type smoke-software to confirm', 'Installing other software reinstalls the server.'] as $text) {
        if (! str_contains($html, $text)) {
            throw new RuntimeException("the page does not show: $text");
        }
    }
    $state = [$test->get('data.type'), $test->get('data.software'), $test->get('data.version')];
    if ($state !== ['plugins', 'paper', '1.21.1']) {
        throw new RuntimeException('the page does not start at the current software: '.json_encode($state));
    }
    if (! $test->instance()->getAction('install')->isDisabled()) {
        throw new RuntimeException('Install works before the name is typed');
    }
    $test->set('data.type', 'vanilla')->set('data.confirm', 'smoke')->call('install');
    if ($test->get('data.type') !== 'vanilla' || ! array_key_exists('data.confirm', $test->errors()->toArray())) {
        throw new RuntimeException('a wrong name was accepted: '.json_encode($test->errors()->toArray()));
    }
    // Without Wings the server is not known to be stopped (and has no backup): nothing changes.
    $test->set('data.confirm', 'smoke-software');
    if ($test->instance()->getAction('install')->isDisabled()) {
        throw new RuntimeException('Install stays disabled after the name is typed');
    }
    $test->call('install');
    if ($test->errors()->isNotEmpty()) {
        throw new RuntimeException('validation: '.json_encode($test->errors()->toArray()));
    }
    if (Startup::of($server->fresh()) !== $before) {
        throw new RuntimeException('the software changed while the server was not stopped');
    }

    // The reinstall cannot reach Wings: the old egg, image, command and variables come back.
    $vanilla = MinecraftEggs::ensure('vanilla', null);
    $failed = false;
    try {
        ChangeSoftware::apply($server, ['egg_id' => $vanilla->id, 'image' => 'ghcr.io/pelican-eggs/yolks:java_21', 'startup' => 'java -jar server.jar', 'environment' => ['VANILLA_VERSION' => '1.21.4']]);
    } catch (Throwable) {
        $failed = true;
    }
    if (! $failed || Startup::of($server->fresh()) !== $before) {
        throw new RuntimeException('the old settings did not come back: '.json_encode(Startup::of($server->fresh())));
    }
    $server->delete();
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
    // On by default; an updated panel may have it off from before.
    Settings::put(['steam_art' => true]);
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
    // A saved server, as after creation: Simplify stores the banner by the server's id.
    $server = Server::factory()->create(['owner_id' => $admin->id, 'egg_id' => $egg->id, 'name' => 'smoke-steam-art']);
    ApplySteamArt::apply($server, $art);
    if (method_exists($server, 'writeIcon')) {
        $path = Server::getIconStoragePath().'/'.$server->uuid.'.png';
        if (! Storage::disk('public')->exists($path) || getimagesizefromstring(Storage::disk('public')->get($path))[0] !== 128) {
            throw new RuntimeException('the Steam icon is not the server icon');
        }
        Storage::disk('public')->delete($path);
    }
    $server->delete();
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

step('an own amount of disk is typed in GB, like RAM, also in a draft from before that (SM-B9)', function () use ($admin) {
    Draft::query()->where('user_id', $admin->id)->delete();
    $test = Livewire\Livewire::actingAs($admin)->test(ServerMaker::class)
        ->call('pickGame', 'minecraft', 'vanilla')
        ->set('data.name', 'smoke-disk')
        ->set('data.disk', 'custom')
        ->set('data.disk_custom', 30);
    if (Resources::payload($test->get('data'))['disk'] !== 30 * 1024) {
        throw new RuntimeException('30 in the field is not 30 GB');
    }
    $test->call('stepDone', 4);
    Livewire\Livewire::actingAs($admin)->test(ServerMaker::class)->assertSet('data.disk', 'custom')->assertSet('data.disk_custom', 30);
    // Until 2.0.0-beta.10 the field was in MB: such a draft opens with the same amount, in GB.
    $draft = Draft::for($admin->id);
    $draft->update(['data' => [...array_diff_key($draft->data, [Resources::DRAFT_GB => true]), 'disk_custom' => 20480]]);
    $test = Livewire\Livewire::actingAs($admin)->test(ServerMaker::class)->assertSet('data.disk_custom', 20);
    if (Resources::payload($test->get('data'))['disk'] !== 20480) {
        throw new RuntimeException('an older draft with 20480 MB is not 20 GB');
    }
    Draft::query()->where('user_id', $admin->id)->delete();
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
    Livewire\Livewire::actingAs($admin)->test(MakerSettingsForm::class)
        ->set('data.self_service', true)
        ->set('data.node_ids', [$node->id])
        ->set('data.eggs', ['minecraft'])
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

step('the starting resources per Minecraft type are saved and used by the wizard', function () use ($admin) {
    Filament\Facades\Filament::setCurrentPanel(Filament\Facades\Filament::getPanel('admin'));
    Livewire\Livewire::actingAs($admin)->test(MakerSettingsForm::class)
        ->assertSee('Starting resources')
        ->assertSet('data.starting.modpack.ram', 6)
        ->set('data.starting.mods.cpu', 300)
        ->set('data.starting.mods.ram', 8)
        ->set('data.starting.mods.disk', 40)
        ->call('save')
        ->assertHasNoErrors();
    if ((Settings::get('starting_resources')['mods'] ?? null) !== ['cpu' => 300, 'ram' => 8, 'disk' => 40960]) {
        throw new RuntimeException('not saved: '.json_encode(Settings::get('starting_resources')));
    }
    Livewire\Livewire::actingAs($admin)->test(ServerMaker::class)
        ->call('pickGame', 'minecraft', 'mods')
        ->assertSet('data.cpu_custom', 300)
        ->assertSet('data.ram_custom', 8)
        ->assertSet('data.disk_custom', 40)
        ->call('pickGame', 'minecraft', 'vanilla')
        ->assertSet('data.ram_custom', 4);
    Settings::put(['starting_resources' => null]);
});

step('Performance shows the limits; administrators change them freely, owners within their allowance', function () use ($admin, $node) {
    $owner = User::where('email', 'smoke-maker-perf@example.com')->first()
        ?? User::factory()->create(['email' => 'smoke-maker-perf@example.com']);
    $owner->syncRoles([]);
    Server::query()->where('owner_id', $owner->id)->delete();
    $server = Server::factory()->create(['owner_id' => $owner->id, 'node_id' => $node->id, 'name' => 'smoke-performance', 'memory' => 4096, 'cpu' => 100, 'disk' => 10240]);

    expectOk(http($admin, Performance::getUrl(panel: 'server', tenant: $server)), 'Performance');
    Filament\Facades\Filament::setCurrentPanel(Filament\Facades\Filament::getPanel('server'));
    Filament\Facades\Filament::setTenant($server);
    // An administrator: any amount, no allowance. Plain checks: a failing PHPUnit assertion cannot report here.
    $test = Livewire\Livewire::actingAs($admin)->test(Performance::class);
    foreach (['Performance', 'New limits apply after the next restart.', '100 %', '4 GB', '10 GB'] as $text) {
        if (! str_contains($test->html(), $text)) {
            throw new RuntimeException("the limits do not show $text");
        }
    }
    if ($test->get('data.ram') !== '4' || ! $test->instance()->getAction('save')->isVisible()) {
        throw new RuntimeException('the page does not start at 4 GB with Save: '.json_encode($test->get('data')));
    }
    $test->set('data.ram', 'custom')->set('data.ram_custom', 24)->call('save');
    if ($test->errors()->isNotEmpty() || (int) $server->fresh()->memory !== 24576 || (int) $server->fresh()->disk !== 10240) {
        throw new RuntimeException('the administrator could not change the RAM: '.json_encode($test->errors()->toArray()).' '.$server->fresh()->memory);
    }

    // The owner with self-service (8 GB RAM in all): up to 8 GB on their only server, not more.
    Settings::put(['self_service' => true, 'role_ids' => [], 'node_ids' => [$node->id], 'max_ram' => 8]);
    $test = Livewire\Livewire::actingAs($owner)->test(Performance::class)
        ->set('data.ram', 'custom')->set('data.ram_custom', 12)->call('save');
    if ($test->errors()->isEmpty() || (int) $server->fresh()->memory !== 24576) {
        throw new RuntimeException('more than the allowance was saved');
    }
    $test->set('data.ram', '8')->call('save');
    if ($test->errors()->isNotEmpty() || (int) $server->fresh()->memory !== 8192) {
        throw new RuntimeException('the owner could not change the RAM within the allowance: '.json_encode($test->errors()->toArray()));
    }

    // Without self-service only the limits, and saving is refused.
    Settings::put(['self_service' => false]);
    $test = Livewire\Livewire::actingAs($owner)->test(Performance::class);
    if (! str_contains($test->html(), 'Ask an administrator to change these.') || $test->instance()->getAction('save')->isVisible()) {
        throw new RuntimeException('without self-service the owner can still change the limits');
    }
    try {
        $test->instance()->save();
        throw new LogicException('saved without self-service');
    } catch (HttpException $exception) {
        if ($exception->getStatusCode() !== 403) {
            throw $exception;
        }
    }
    Settings::put(['self_service' => true]);
    $server->delete();
});

step('Performance gives a server extra ports from the free ports of its node, and takes the newest away', function () use ($admin, $node) {
    $owner = User::where('email', 'smoke-maker-perf@example.com')->first()
        ?? User::factory()->create(['email' => 'smoke-maker-perf@example.com']);
    $owner->syncRoles([]);
    Allocation::query()->where('node_id', $node->id)->whereBetween('port', [29999, 30002])->delete();
    // Made before the panel has a tenant: with the server as tenant, Filament gives new allocations to that server.
    foreach ([30001, 30002, 29999] as $port) {
        Allocation::query()->create(['node_id' => $node->id, 'ip' => '127.0.0.1', 'port' => $port]);
    }
    $main = Allocation::query()->create(['node_id' => $node->id, 'ip' => '127.0.0.1', 'port' => 30000]);
    $server = Server::factory()->create(['owner_id' => $owner->id, 'node_id' => $node->id, 'name' => 'smoke-ports', 'allocation_id' => $main->id, 'memory' => 4096, 'cpu' => 100, 'disk' => 10240]);
    // The server factory can bring its own allocation: make 30000 the main port.
    Allocation::query()->where('server_id', $server->id)->update(['server_id' => null]);
    $main->forceFill(['server_id' => $server->id])->save();
    Server::query()->whereKey($server->id)->update(['allocation_id' => $main->id]);
    $server->refresh();
    $ports = fn () => Allocation::query()->where('server_id', $server->id)->orderBy('port')->pluck('port')->map(fn ($port) => (int) $port)->all();
    expectOk(http($admin, Performance::getUrl(panel: 'server', tenant: $server)), 'Performance');

    Filament\Facades\Filament::setCurrentPanel(Filament\Facades\Filament::getPanel('server'));
    Filament\Facades\Filament::setTenant($server);
    $test = Livewire\Livewire::actingAs($admin)->test(Performance::class);
    if ($ports() !== [30000] || (int) $test->get('data.extra_ports') !== 0) {
        throw new RuntimeException('the server does not start with only its main port: '.json_encode($ports()).' '.json_encode($test->get('data.extra_ports')));
    }
    if (! str_contains($test->html(), 'data-simplify-unsaved')) {
        throw new RuntimeException('Performance does not ask Save or Discard before leaving with changes');
    }
    if (! preg_match('/30000\s*·\s*main/u', $test->html())) {
        throw new RuntimeException('the main port does not show: '.substr(strip_tags((string) strstr($test->html(), 'smk-field-ports') ?: 'no ports field'), 0, 300));
    }
    // Two extra ports: those right after the main port.
    $test->set('data.extra_ports', 2)->call('save');
    if ($test->errors()->isNotEmpty() || $ports() !== [30000, 30001, 30002]) {
        throw new RuntimeException('the extra ports were not given: '.json_encode($test->errors()->toArray()).' '.json_encode($ports()));
    }
    // More than the node has free: refused, nothing changes.
    $test->set('data.extra_ports', ExtraPorts::MAX)->call('save');
    if (! $test->errors()->has('data.extra_ports') || $ports() !== [30000, 30001, 30002]) {
        throw new RuntimeException('more ports than free were accepted: '.json_encode($ports()));
    }

    // The owner may have one extra port: two is refused, one takes the newest away, the main port stays.
    Settings::put(['self_service' => true, 'role_ids' => [], 'node_ids' => [$node->id], 'max_ports' => 1]);
    $test = Livewire\Livewire::actingAs($owner)->test(Performance::class)->set('data.extra_ports', 2)->call('save');
    if ($test->errors()->isEmpty()) {
        throw new RuntimeException('more ports than the allowance were accepted');
    }
    $test->set('data.extra_ports', 1)->call('save');
    if ($test->errors()->isNotEmpty() || count($ports()) !== 2 || ! in_array(30000, $ports(), true)) {
        throw new RuntimeException('the owner could not lower the ports: '.json_encode($test->errors()->toArray()).' '.json_encode($ports()));
    }
    $test->set('data.extra_ports', 0)->call('save');
    if ($ports() !== [30000]) {
        throw new RuntimeException('the main port did not stay alone: '.json_encode($ports()));
    }
    Settings::put(['max_ports' => 0]);
    $server->delete();
    Allocation::withoutGlobalScopes()->where('node_id', $node->id)->whereBetween('port', [29999, 30002])->delete();
});

step('a Steam key is checked, stored encrypted, never shown again, and makes login tokens', function () use ($admin) {
    $key = 'ABCDEF0123456789ABCDEF0123456789';
    $calls = [];
    SteamKeys::$transport = function (string $method, string $url, array $params) use (&$calls): array {
        $calls[] = [$method, basename(dirname($url)), $params['appid'] ?? null, $params['memo'] ?? null];

        return str_contains($url, 'GetAccountList')
            ? [200, ['response' => ['actor' => '76561190000000001']]]
            : [200, ['response' => ['login_token' => 'SMOKETOKEN0123456789ABCD']]];
    };
    SteamKeys::remove($admin);
    try {
        SteamKeys::save($admin, 'not-a-key');
        throw new LogicException('a wrong key was accepted');
    } catch (ValidationException) {
    }
    SteamKeys::save($admin, strtolower($key));
    $status = SteamKeys::status($admin);
    if (! $status['connected'] || $status['hint'] !== '6789' || $status['steamid'] !== '76561190000000001' || ! $status['auto']) {
        throw new RuntimeException('not connected: '.json_encode($status));
    }
    $raw = (string) DB::table('simplify_server_maker_settings')->where('key', 'steam_key.'.$admin->id)->value('value');
    if ($raw === '' || str_contains($raw, $key)) {
        throw new RuntimeException('the key is stored readable');
    }

    // The page shows only the last four characters. Since Connections (add-on API 6) there is no Steam page.
    $response = http($admin, Steam::getUrl(panel: 'app'));
    if (CoreApi::atLeast(6)) {
        if ($response->getStatusCode() === 200) {
            throw new RuntimeException('the Steam page opens next to the Steam row on Connections');
        }
    } else {
        expectOk($response, 'Steam');
        if (! str_contains($response->getContent(), 'ending in 6789') || str_contains($response->getContent(), $key)) {
            throw new RuntimeException('the Steam page does not show the hint, or shows the key');
        }
    }

    // A token for the game of an egg: Steam's own app of the game (CS2 is 730), the server name as memo.
    Cache::put('simplify-servermaker.steam.'.md5('counter-strike 2'), ['appid' => 730, 'name' => 'Counter-Strike 2', 'banner' => '', 'header' => '', 'capsule' => ''], 60);
    $token = SteamKeys::createForGame($admin, 'Counter-Strike 2', 'smoke-cs2');
    if ($token !== 'SMOKETOKEN0123456789ABCD' || end($calls) !== ['POST', 'CreateAccount', 730, 'smoke-cs2']) {
        throw new RuntimeException('no token: '.json_encode($calls));
    }
    foreach (glob(storage_path('logs/*.log')) ?: [] as $log) {
        if (str_contains((string) file_get_contents($log), $key)) {
            throw new RuntimeException('the key is in '.basename($log));
        }
    }
    SteamKeys::remove($admin);
    SteamKeys::$transport = null;
    if (SteamKeys::connected($admin)) {
        throw new RuntimeException('Disconnect kept the key');
    }
});

step('the Steam row saves only from its popup: keep the key, switch automatic tokens, disconnect from the menu', function () use ($admin) {
    $key = 'ABCDEF0123456789ABCDEF0123456789';
    SteamKeys::$transport = fn (string $method, string $url, array $params): array => [200, ['response' => ['actor' => '76561190000000001']]];
    SteamKeys::remove($admin);
    Filament\Facades\Filament::setCurrentPanel(Filament\Facades\Filament::getPanel('app'));
    $row = Livewire\Livewire::actingAs($admin)->test(SteamConnection::class);
    $row->assertActionHidden('disconnect');
    if (! str_contains($row->html(), 'No key connected')) {
        throw new RuntimeException('a row without a key does not say so');
    }
    $row->callAction('connect', data: ['key' => '', 'auto' => true])->assertHasActionErrors(['key' => 'required']);
    // The popup stays open on the error: fill in the key there and save.
    $row->set('mountedActions.0.data.key', strtolower($key))->callMountedAction()->assertHasNoErrors();
    if (! str_contains($row->html(), 'ending in 6789') || str_contains($row->html(), $key) || ! SteamKeys::autoTokens($admin)) {
        throw new RuntimeException('the row does not show the connected key by its last four characters');
    }
    // A connected key: an empty field keeps it, and the switch is saved with the popup's Save only.
    $row->mountAction('connect')->set('mountedActions.0.data.auto', false);
    if (! SteamKeys::autoTokens($admin)) {
        throw new RuntimeException('switching off automatic tokens saved itself');
    }
    $row->callMountedAction()->assertHasNoErrors();
    if (SteamKeys::autoTokens($admin) || SteamKeys::status($admin)['hint'] !== '6789') {
        throw new RuntimeException('Save did not keep the key and switch off automatic tokens: '.json_encode(SteamKeys::status($admin)));
    }
    if (! CoreApi::atLeast(6)) {
        $page = http($admin, Steam::getUrl(panel: 'app'));
        expectOk($page, 'Steam');
        if (! str_contains($page->getContent(), 'smk-connection') || ! str_contains($page->getContent(), 'ending in 6789')) {
            throw new RuntimeException('the Steam page does not show the Steam row');
        }
    }
    $row->callAction('disconnect')->assertHasNoErrors();
    if (SteamKeys::connected($admin)) {
        throw new RuntimeException('Disconnect kept the key');
    }
    SteamKeys::$transport = null;
});

step('nothing saves itself: the settings wait for Save', function () use ($admin) {
    Filament\Facades\Filament::setCurrentPanel(Filament\Facades\Filament::getPanel('admin'));
    $html = Livewire\Livewire::actingAs($admin)->test(MakerSettingsForm::class)->html();
    if (! str_contains($html, 'data-simplify-unsaved') || ! str_contains($html, 'data-simplify-save="save"') || str_contains($html, '<form')) {
        throw new RuntimeException('the settings do not ask Save or Discard before leaving, or bring a form of their own into Simplify settings');
    }
    // The settings page's Save goes to the form.
    Livewire\Livewire::actingAs($admin)->test(MakerSettings::class)->callAction('save')->assertDispatchedTo(MakerSettingsForm::class, MakerSettingsForm::SAVE);
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

step('a user without servers left is stopped, and the Review step says why (SM-B4)', function () use ($user, $node) {
    Settings::put(['max_servers' => 0]);
    Filament\Facades\Filament::setCurrentPanel(Filament\Facades\Filament::getPanel('app'));
    session()->forget('filament.notifications');
    $test = Livewire\Livewire::actingAs($user)->test(AppServerMaker::class)
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
    // The name is a field of another step: on Review its error is out of view, so a message says it as well.
    try {
        $test->assertNotified(__('simplify-servermaker::maker.review.failed'));
    } catch (Throwable) {
        throw new RuntimeException('Create stopped on a field of another step without a message on Review');
    }
});

step('only the step of the wizard scrolls, also past a file field or a hidden input (SM-B5)', function () {
    // A part that is placed absolutely is not cut off by the step it scrolls in, unless the step is what it is placed
    // against: without that the popup itself scrolled on a phone, with its bar of buttons.
    if (! preg_match('/html\.smk-fit \.smk \.fi-sc-wizard-step \{[^}]*position: relative;/', view('simplify-servermaker::wizard.styles')->render())) {
        throw new RuntimeException('the step of the wizard is not what its absolutely placed parts are placed against');
    }
});

step("without Simplify, Pelican's user menu has Create server for people who may make servers (SM-B6)", function () use ($user) {
    // With Simplify the overview has the Create server button (a step below) and Pelican's user menu is not shown.
    $core = class_exists(Addon::class);
    $item = ServerMakerPlugin::makerMenu()['simplify-servermaker'];
    auth()->setUser($user);
    Settings::put(['self_service' => true]);
    if ($item->isVisible() === $core) {
        throw new RuntimeException($core ? "with Simplify, Pelican's user menu has Create server as well" : 'a user who may make servers has no way to the Server Maker');
    }
    if (! $core) {
        $response = http($user, '/');
        expectOk($response, '/');
        if (! str_contains((string) $response->getContent(), AppServerMaker::getUrl(panel: 'app'))) {
            throw new RuntimeException("Create server is not in the user menu on Pelican's page");
        }
    }
    Settings::put(['self_service' => false]);
    try {
        if ($item->isVisible()) {
            throw new RuntimeException('a user who may not make servers is offered Create server');
        }
    } finally {
        Settings::put(['self_service' => true]);
    }
});

step("with Simplify, Pelican's own form at admin › Servers › Create opens the Server Maker instead, also by its address (SM-B8)", function () use ($admin) {
    $create = route('filament.admin.resources.servers.create');
    $list = route('filament.admin.resources.servers.index');
    $response = http($admin, $create);
    if (! class_exists(Addon::class)) {
        // Without Simplify Pelican's form stays: the Server Maker has its own place in the menus.
        expectOk($response, $create);

        return;
    }
    if (! $response->isRedirect($list) || $response->getSession()?->get(OpenServerMaker::OPEN) !== true) {
        throw new RuntimeException('HTTP '.$response->getStatusCode().": Pelican's form opened by its address");
    }
    // The list of servers then asks Simplify for its Create server popup, once.
    $opens = fn (array $session): bool => str_contains((string) http($admin, $list, $session)->getContent(), "CustomEvent('simplify:create')");
    if (! $opens([OpenServerMaker::OPEN => true]) || $opens([])) {
        throw new RuntimeException('the list of servers does not open Create server once');
    }
    // Simplify's legacy panel shows Pelican's own pages: there the form stays.
    expectOk(http($admin, $create, ['simplify_legacy' => true]), $create);
});

step('an empty list of nodes or eggs means all of them; only administrators add eggs from the index', function () use ($user, $node) {
    Settings::put(['self_service' => true, 'role_ids' => [], 'node_ids' => [], 'eggs' => [], 'max_servers' => null, 'import_eggs' => true]);
    Filament\Facades\Filament::setCurrentPanel(Filament\Facades\Filament::getPanel('app'));
    auth()->setUser($user);
    if (! Access::canUse($user) || ! in_array($node->id, Ports::nodes()->pluck('id')->all(), true)) {
        throw new RuntimeException('with no nodes chosen the user cannot make a server on every node');
    }
    if (! Access::minecraft() || OtherGames::query()->count() !== Egg::query()->count() || EggIndex::canInstall()) {
        throw new RuntimeException('with no eggs chosen the user does not get every egg, or may add eggs from the index');
    }
    // Only Minecraft: no other eggs; adding the bundled Minecraft eggs stays.
    Settings::put(['eggs' => ['minecraft']]);
    if (! Access::minecraft() || OtherGames::query()->count() !== 0 || ! Access::mayImportEggs()) {
        throw new RuntimeException('Minecraft only lets more through');
    }
    // One other egg and no Minecraft.
    $egg = Egg::query()->get()->first(fn (Egg $egg) => ! Minecraft::isMinecraftEgg((string) $egg->name));
    Settings::put(['eggs' => [$egg->id]]);
    if (Access::minecraft() || OtherGames::query()->pluck('id')->all() !== [$egg->id]) {
        throw new RuntimeException('one chosen egg does not limit the choice to that egg');
    }
    Settings::put(['node_ids' => [$node->id], 'eggs' => ['minecraft'], 'import_eggs' => null]);
});

step("in Simplify's friends mode users may add eggs from the index, while every egg is allowed", function () use ($user, $node) {
    if (! class_exists(PanelSettings::class)) {
        require_once dirname(__DIR__).'/stubs/panel-settings.php';
    }
    Settings::put(['self_service' => true, 'role_ids' => [], 'node_ids' => [], 'eggs' => [], 'import_eggs' => true]);
    auth()->setUser($user);
    // The real core keeps friends mode as a setting; the stand-in (without the core) as a static flag.
    $friends = function (bool $on): void {
        if (property_exists(PanelSettings::class, 'friends')) {
            PanelSettings::$friends = $on;
        } else {
            PanelSettings::save(['friends_mode' => $on]);
        }
    };
    $can = function (bool $on) use ($friends): bool {
        $friends($on);

        return EggIndex::canInstall();
    };
    if (! $can(true) || $can(false)) {
        throw new RuntimeException('friends mode does not decide');
    }
    // Not with a limited egg list, and not without "Add missing eggs".
    Settings::put(['eggs' => ['minecraft']]);
    $limited = $can(true);
    Settings::put(['eggs' => [], 'import_eggs' => false]);
    if ($limited || $can(true)) {
        throw new RuntimeException('index eggs are offered with a limited egg list or with Add missing eggs off');
    }
    $friends(false);
    Settings::put(['node_ids' => [$node->id], 'eggs' => ['minecraft'], 'import_eggs' => null]);
});

step('updating from the switch for Minecraft and the list of other games keeps the old choice', function () use ($node) {
    $migration = require dirname(__DIR__, 2).'/database/migrations/2026_11_03_000001_simplify_server_maker_one_egg_list.php';
    $old = fn (array $values) => Settings::put([...$values, 'eggs' => null]);
    // Minecraft on, two other games, a node: the same choice in the one list, self-service stays on.
    $old(['self_service' => true, 'minecraft' => true, 'egg_ids' => [5, 9], 'node_ids' => [$node->id]]);
    $migration->up();
    if (Settings::get('eggs') !== ['minecraft', 5, 9] || Settings::get('self_service') !== true || Settings::get('minecraft') !== null || Settings::get('egg_ids') !== null) {
        throw new RuntimeException('not carried over: '.json_encode([Settings::get('eggs'), Settings::get('self_service')]));
    }
    // Run again: nothing changes.
    Settings::put(['eggs' => ['minecraft']]);
    $migration->up();
    if (Settings::get('eggs') !== ['minecraft']) {
        throw new RuntimeException('the migration ran twice');
    }
    // Minecraft off and no other games, or no nodes: nobody could make a server, and that stays so.
    foreach ([['minecraft' => false, 'egg_ids' => [], 'node_ids' => [$node->id]], ['minecraft' => true, 'egg_ids' => [], 'node_ids' => []]] as $values) {
        $old(['self_service' => true, ...$values]);
        $migration->up();
        if (Settings::get('self_service') !== false || Settings::get('eggs') !== ['minecraft']) {
            throw new RuntimeException('a setup that let nobody in opened up: '.json_encode($values));
        }
    }
    Settings::put(['self_service' => true, 'node_ids' => [$node->id]]);
});

step('an error the Server Maker shows can be explained by its own user and root admins only, the trace by root admins', function () use ($admin, $user) {
    $other = User::where('email', 'smoke-maker-other@example.com')->first()
        ?? User::factory()->create(['email' => 'smoke-maker-other@example.com']);
    $problems = new ServerMakerProblems;
    $id = ProblemLog::record('create', $user, 'The node did not answer', new RuntimeException('Wings said no'));
    if (! preg_match('/^simplify-servermaker:create:[A-Za-z0-9]{24}$/', $id)) {
        throw new RuntimeException('not a problem id: '.$id);
    }
    if (! $problems->canExplainProblem($id, $user) || ! $problems->canExplainProblem($id, $admin) || $problems->canExplainProblem($id, $other)) {
        throw new RuntimeException('the wrong people may explain the error');
    }
    $mine = $problems->explainProblem($id, $user);
    $root = $problems->explainProblem($id, $admin);
    if ($mine === null || $mine['lines'] !== ['The node did not answer'] || $mine['title'] !== 'The server could not be created') {
        throw new RuntimeException('the user does not get only the message: '.json_encode($mine));
    }
    if ($root === null || ! str_contains(implode("\n", $root['lines']), 'RuntimeException: Wings said no') || count($root['lines']) > 100) {
        throw new RuntimeException('a root admin does not get the trace: '.json_encode($root));
    }
    foreach (['simplify-servermaker:create:short', 'simplify-servermaker:delete:'.substr($id, -24), 'simplify:create:'.substr($id, -24), str_replace(':create:', ':token:', $id)] as $wrong) {
        if ($problems->canExplainProblem($wrong, $admin) || $problems->explainProblem($wrong, $admin) !== null) {
            throw new RuntimeException('a wrong id is accepted: '.$wrong);
        }
    }
    if (! class_exists('Simplify\\Explain\\Problems\\Problems') && ProblemLog::explainActions($id) !== []) {
        throw new RuntimeException('without Explain the error notification still gets an Explain button');
    }
    if (! in_array(ServerMakerProblems::class, array_map(fn ($source) => $source::class, iterator_to_array(app()->tagged('simplify.problems'))), true)) {
        throw new RuntimeException('the problem source is not tagged for Simplify-Explain');
    }
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
    if ($addon->settingsForm() !== MakerSettingsForm::class) {
        throw new RuntimeException('Simplify settings do not get the Server Maker settings form');
    }
    // Steam is a row on Connections (add-on API 6); a Simplify before that (the stub is API 5) links the Steam page instead.
    if ($addon->connections($admin) !== [SteamConnection::class] || count($addon->accountPages($admin)) !== (defined(Addon::class.'::API') && Addon::API >= 6 ? 0 : 1)) {
        throw new RuntimeException('Steam is not a Connections row, or a Simplify before Connections has no Steam page');
    }
    auth()->setUser($user);
    if ($addon->dashboardActions() !== [] || $addon->settingsUrl() !== null) {
        throw new RuntimeException('a user without self-service gets the button');
    }
    // With Simplify its settings open from Simplify: no settings button above the wizard in Simplify's popup.
    Filament\Facades\Filament::setCurrentPanel(Filament\Facades\Filament::getPanel('admin'));
    Livewire\Livewire::actingAs($admin)->test(ServerMaker::class)->assertActionHidden('settings');
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
