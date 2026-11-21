<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('submissions', fn (Blueprint $t) => $t->date('status_on')->nullable()->after('submitted_on'));
    }

    public function down(): void
    {
        Schema::table('submissions', fn (Blueprint $t) => $t->dropColumn('status_on'));
    }
};
