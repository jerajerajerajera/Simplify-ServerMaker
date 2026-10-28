<?php

// Loads the plugin inside a real Pelican panel and checks what unit tests cannot: the panel loads the code, the
// translations and the pages. Run from the panel folder after `php artisan p:plugin:install simplify-server-maker`:
// php plugins/simplify-server-maker/tests/panel/smoke.php

use App\Enums\EggFormat;
use App\Models\Allocation;
use App\Models\Egg;
use App\Models\Node;
use App\Models\Server;
use App\Models\User;
use App\Services\Eggs\Sharing\EggImporterService;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Foundation\Testing\Concerns\InteractsWithAuthentication;
use Illuminate\Foundation\Testing\Concerns\InteractsWithSession;
use Illuminate\Foundation\Testing\Concerns\MakesHttpRequests;
use Illuminate\Testing\TestResponse;
use Simplify\ServerMaker\Creation\Ports;
use Simplify\ServerMaker\Filament\Admin\Pages\ServerMaker;
use Simplify\ServerMaker\Games\BundledEggs;
use Simplify\ServerMaker\Games\OtherGames;
use Simplify\ServerMaker\ServerMakerAddon;

define('LARAVEL_START', microtime(true));
require getcwd().'/vendor/autoload.php';
$app = require getcwd().'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$failed = false;

function step(string $what, callable $run): void
{
    global $failed;
    try {
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

// Anything that fails outside a step (for example making the test user) must fail the job too.
set_exception_handler(function (Throwable $e): void {
    echo 'FAIL setup: '.get_class($e).': '.substr($e->getMessage(), 0, 400)."\n";
    exit(1);
});

$admin = User::where('email', 'smoke-maker@example.com')->first()
    ?? User::factory()->create(['email' => 'smoke-maker@example.com']);
$admin->syncRoles(App\Models\Role::getRootAdmin());

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

exit($failed ? 1 : 0);
