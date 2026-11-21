<?php

// The update path with settings filled in, as on a real panel. Run from the panel folder with the plugin installed:
//   fill   with the old version installed: saves settings in that version's own format
//   check  after the new version is installed over it: every setting is still there, carried over to the new format
//   pages  any version, also an older one put back over newer tables: the Server Maker's pages open
// php <this file> fill|check|pages

use App\Models\Role;
use App\Models\User;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Foundation\Testing\Concerns\InteractsWithAuthentication;
use Illuminate\Foundation\Testing\Concerns\InteractsWithSession;
use Illuminate\Foundation\Testing\Concerns\MakesHttpRequests;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Simplify\ServerMaker\Access;
use Simplify\ServerMaker\Creation\ApplyFirstSettings;
use Simplify\ServerMaker\Creation\Resources;
use Simplify\ServerMaker\Models\Draft;
use Simplify\ServerMaker\Models\WaitingSettings;
use Simplify\ServerMaker\Settings;

define('LARAVEL_START', microtime(true));
require getcwd().'/vendor/autoload.php';
$app = require getcwd().'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

// Not a real key: only checks that a secret survives the update encrypted.
const CURSEFORGE = 'ci-upgrade-curseforge-key';
const LIMITS = ['max_servers' => 5, 'max_ram' => 16, 'max_disk' => 80, 'max_cpu' => 400, 'max_backups' => 3];

// The id of a server that does not exist: only its queued first settings travel through the update.
const SERVER = 987654;

function admin(): User
{
    $admin = User::where('email', 'smoke-maker@example.com')->first()
        ?? User::factory()->create(['email' => 'smoke-maker@example.com']);
    $admin->syncRoles(Role::getRootAdmin());

    return $admin;
}

/**
 * A fresh application per page, like a real request: Filament keeps panel state for the whole process.
 *
 * @return array{int, string}
 */
function open_page(string $url): array
{
    $app = require getcwd().'/bootstrap/app.php';
    $app->make(Kernel::class)->bootstrap();
    $admin = admin();
    $client = new class($app)
    {
        use InteractsWithAuthentication;
        use InteractsWithSession;
        use MakesHttpRequests;

        public function __construct(public $app) {}
    };
    $response = $client->actingAs($admin)->get($url);

    return [$response->getStatusCode(), $response->exception ? ': '.substr($response->exception->getMessage(), 0, 300) : ''];
}

$failed = false;
$check = function (string $what, bool $ok) use (&$failed): void {
    echo ($ok ? 'PASS ' : 'FAIL ').$what."\n";
    $failed = $failed || ! $ok;
};

switch ($argv[1] ?? '') {
    case 'fill':
        // Until 2.0.0-beta.2 Minecraft had its own switch next to a list of other eggs; since then one list.
        $eggs = defined(Access::class.'::MINECRAFT')
            ? ['eggs' => ['minecraft', 2]]
            : ['minecraft' => true, 'egg_ids' => [2]];
        Settings::put([
            'self_service' => true,
            'role_ids' => [],
            'node_ids' => [1],
            ...$eggs,
            'import_eggs' => true,
            'steam_art' => false,
            'curseforge_key' => CURSEFORGE,
            ...LIMITS,
        ]);
        $check('the old version saved its settings', Settings::get('curseforge_key') === CURSEFORGE);
        // A server made just before the update: its first settings wait in a queued job (until 2.0.0-beta.10), and a
        // wizard left halfway with an own amount of disk, then in MB. The job waits, so the install job goes first.
        config(['queue.default' => 'database']);
        ApplyFirstSettings::dispatch(SERVER, ['eula' => true])->delay(now()->addHour());
        $check('the old version queued the first settings', DB::table('jobs')->where('payload', 'like', '%ApplyFirstSettings%')->exists());
        Draft::query()->updateOrCreate(['user_id' => admin()->id], ['data' => ['name' => 'upgrade-draft', 'disk' => 'custom', 'disk_custom' => 20480], 'step' => 4]);
        break;
    case 'check':
        $check('self-service stays on', Settings::get('self_service') === true);
        $check('the nodes stay', Settings::get('node_ids') === [1]);
        $check('Minecraft and the other egg are one list', Settings::get('eggs') === ['minecraft', 2]);
        $check('the old Minecraft switch and egg list are gone', Settings::get('minecraft') === null && Settings::get('egg_ids') === null);
        $check('adding eggs and Steam art stay', Settings::get('import_eggs') === true && Settings::get('steam_art') === false);
        foreach (LIMITS as $key => $value) {
            $check("the allowance $key stays", Settings::get($key) === $value);
        }
        $check('the CurseForge key stays, encrypted', Settings::get('curseforge_key') === CURSEFORGE);
        // The queued job of the old version hands its settings to the scheduler (SM-B7), which drops them here: the
        // server does not exist.
        DB::table('jobs')->where('payload', 'like', '%ApplyFirstSettings%')->update(['available_at' => time() - 1]);
        Artisan::call('queue:work', ['--stop-when-empty' => true]);
        $check('the first settings a job of the old version held now wait for the scheduler', WaitingSettings::query()->where('server_id', SERVER)->value('settings') === ['eula' => true]
            && ! DB::table('jobs')->where('payload', 'like', '%ApplyFirstSettings%')->exists() && ! DB::table('failed_jobs')->where('payload', 'like', '%ApplyFirstSettings%')->exists());
        Artisan::call('simplify-servermaker:first-settings');
        $check('the scheduler drops the first settings of a server that is gone', ! WaitingSettings::query()->where('server_id', SERVER)->exists());
        // The draft's own amount of disk was in MB and is in GB now (SM-B9).
        $draft = Resources::fromDraft(Draft::for((int) admin()->id)->data ?? []);
        $check('a draft of the old version keeps its own amount of disk: 20480 MB is 20 GB', ($draft['disk_custom'] ?? null) === 20 && Resources::payload([...$draft, 'ram' => '4', 'cpu' => '100'])['disk'] === 20480);
        Draft::query()->where('user_id', admin()->id)->delete();
        break;
    case 'pages':
        foreach (['/admin', '/admin/server-maker', '/admin/server-maker/settings', '/server-maker'] as $url) {
            [$status, $detail] = open_page($url);
            $check("$url opens".($status === 200 ? '' : " (HTTP $status$detail)"), $status === 200);
        }
        break;
    default:
        echo "Usage: upgrade.php fill|check|pages\n";
        exit(1);
}

echo $failed ? "Some steps failed\n" : "All steps passed\n";
exit($failed ? 1 : 0);
