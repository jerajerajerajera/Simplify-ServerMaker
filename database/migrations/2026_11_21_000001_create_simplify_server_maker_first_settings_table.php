<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('simplify_server_maker_first_settings')) {
            return;
        }

        Schema::create('simplify_server_maker_first_settings', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('server_id')->unique();
            // Encrypted, as drafts are. Holds the uploaded server icon too, so it needs more room than a text column.
            $table->mediumText('settings');
            $table->timestamps();
        });
    }

    // Uninstalling the plugin rolls its migrations back; the table stays, as the other tables of the Server Maker do.
    public function down(): void {}
};
