<?php

// After `php artisan p:plugin:uninstall simplify-servermaker`: Pelican's own pages still open and the Server Maker is
// gone from them, while its settings and drafts stay in the database. Run from the panel folder.

use App\Models\Role;
use App\Models\User;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Foundation\Testing\Concerns\InteractsWithAuthentication;
use Illuminate\Foundation\Testing\Concerns\InteractsWithSession;
use Illuminate\Foundation\Testing\Concerns\MakesHttpRequests;
use Illuminate\Support\Facades\Schema;

define('LARAVEL_START', microtime(true));
require getcwd().'/vendor/autoload.php';

// A fresh application per page, like a real request: Filament keeps panel state for the whole process, so an admin
// page and then an app page in one application can fail where a browser would not.
function status(string $url): int
{
    $app = require getcwd().'/bootstrap/app.php';
    $app->make(Kernel::class)->bootstrap();
    $admin = User::where('email', 'smoke-maker@example.com')->first()
        ?? User::factory()->create(['email' => 'smoke-maker@example.com']);
    $admin->syncRoles(Role::getRootAdmin());
    $client = new class($app)
    {
        use InteractsWithAuthentication;
        use InteractsWithSession;
        use MakesHttpRequests;

        public function __construct(public $app) {}
    };

    return $client->actingAs($admin)->get($url)->getStatusCode();
}

$failed = false;
$check = function (string $what, bool $ok) use (&$failed): void {
    echo ($ok ? 'PASS ' : 'FAIL ').$what."\n";
    $failed = $failed || ! $ok;
};

foreach (['/admin', '/'] as $url) {
    // With Simplify the start page can send you on (a redirect); an error page is a failure.
    $status = status($url);
    $check("Pelican's page $url opens (HTTP $status)", $status < 400);
}
$check('the Server Maker is gone from the admin', status('/admin/server-maker') !== 200);
foreach (['simplify_server_maker_settings', 'simplify_server_maker_drafts'] as $table) {
    $check("the table $table stays", Schema::hasTable($table));
}

echo $failed ? "Some steps failed\n" : "All steps passed\n";
exit($failed ? 1 : 0);
