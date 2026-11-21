<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $t) {
            $t->json('scholar_stats')->nullable()->after('scholar_url');
            $t->timestamp('scholar_checked_at')->nullable()->after('scholar_stats');
        });
    }

    public function down(): void
    {
        Schema::table('users', fn (Blueprint $t) => $t->dropColumn(['scholar_stats', 'scholar_checked_at']));
    }
};
