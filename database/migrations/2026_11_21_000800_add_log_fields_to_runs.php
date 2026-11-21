<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['scans', 'ai_runs'] as $t) {
            Schema::table($t, function (Blueprint $table) {
                $table->string('provider')->nullable();
                $table->text('log')->nullable();
            });
        }
    }

    public function down(): void
    {
        foreach (['scans', 'ai_runs'] as $t) {
            Schema::table($t, fn (Blueprint $table) => $table->dropColumn(['provider', 'log']));
        }
    }
};
