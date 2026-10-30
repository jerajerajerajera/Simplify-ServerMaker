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

        // Settings for a new server that wait until its installation is done; removed once applied.
        Schema::create('simplify_server_maker_first_settings', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('server_id')->unique();
            $table->mediumText('settings');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('simplify_server_maker_first_settings');
    }
};
