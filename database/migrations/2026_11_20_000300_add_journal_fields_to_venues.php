<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('venues', function (Blueprint $table) {
            $table->string('quartile', 8)->nullable()->after('rank');
            $table->string('first_decision')->nullable()->after('acceptance_rate');
            $table->string('proceedings')->nullable()->after('first_decision');
        });
    }

    public function down(): void
    {
        Schema::table('venues', fn (Blueprint $table) => $table->dropColumn(['quartile', 'first_decision', 'proceedings']));
    }
};
