<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Kept when the plugin is reinstalled or takes over an older copy, so the settings stay.
        if (Schema::hasTable('simplify_server_maker_settings')) {
            return;
        }

        Schema::create('simplify_server_maker_settings', function (Blueprint $table) {
            $table->string('key', 64)->primary();
            $table->text('value');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('simplify_server_maker_settings');
    }
};
