<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('papers', fn (Blueprint $t) => $t->date('started_on')->nullable()->after('stage'));

        // Existing papers: the first submission if there is one, otherwise the day the paper was added.
        foreach (DB::table('papers')->get(['id', 'created_at']) as $p) {
            $first = DB::table('submissions')->where('paper_id', $p->id)->whereNotNull('submitted_on')->min('submitted_on');
            DB::table('papers')->where('id', $p->id)->update(['started_on' => $first ?? substr((string) $p->created_at, 0, 10) ?: null]);
        }
    }

    public function down(): void
    {
        Schema::table('papers', fn (Blueprint $t) => $t->dropColumn('started_on'));
    }
};
