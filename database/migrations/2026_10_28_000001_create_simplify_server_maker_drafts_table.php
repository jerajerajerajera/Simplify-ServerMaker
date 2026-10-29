<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Kept when the plugin is reinstalled or takes over an older copy, so the settings stay.
        if (Schema::hasTable('simplify_server_maker_drafts')) {
            return;
        }

        Schema::create('simplify_server_maker_drafts', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('user_id')->unique();
            // Encrypted: game settings can hold passwords and tokens.
            $table->text('data');
            $table->unsignedTinyInteger('step')->default(1);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('simplify_server_maker_drafts');
    }
};
