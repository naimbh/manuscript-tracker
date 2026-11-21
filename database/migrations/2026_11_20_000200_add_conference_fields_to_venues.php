<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('venues', function (Blueprint $table) {
            $table->string('field')->nullable()->after('kind');
            $table->string('publisher')->nullable()->after('field');
            $table->string('rank')->nullable()->after('publisher');
            $table->string('acceptance_rate')->nullable()->after('rank');
            $table->string('next_edition')->nullable()->after('acceptance_rate');
            $table->date('deadline_on')->nullable()->after('next_edition');
            $table->string('deadline_label')->nullable()->after('deadline_on');
            $table->boolean('deadline_expected')->default(false)->after('deadline_label');
            $table->string('website_url')->nullable()->after('portal_url');
            $table->boolean('watch')->default(false)->after('website_url');
            $table->timestamp('checked_at')->nullable()->after('watch');
            $table->json('sources')->nullable()->after('checked_at');
        });
    }

    public function down(): void
    {
        Schema::table('venues', function (Blueprint $table) {
            $table->dropColumn(['field', 'publisher', 'rank', 'acceptance_rate', 'next_edition', 'deadline_on',
                'deadline_label', 'deadline_expected', 'website_url', 'watch', 'checked_at', 'sources']);
        });
    }
};
