<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', fn (Blueprint $t) => $t->string('scholar_url')->nullable()->after('interests'));

        Schema::create('publications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('paper_id')->nullable()->constrained()->nullOnDelete();
            $table->string('title', 500);
            $table->string('authors')->nullable();
            $table->string('kind')->default('article'); // article, conference, thesis, letter, chapter, preprint
            $table->string('venue')->nullable();
            $table->string('volume', 40)->nullable();
            $table->string('issue', 40)->nullable();
            $table->string('pages', 40)->nullable();
            $table->unsignedSmallInteger('year')->nullable();
            $table->string('publisher')->nullable();
            $table->string('quartile', 8)->nullable();
            $table->string('sjr', 20)->nullable();
            $table->string('abs', 20)->nullable();
            $table->string('abdc', 20)->nullable();
            $table->unsignedInteger('citations')->nullable();
            $table->string('scholar_url', 500)->nullable();
            $table->string('cites_url', 500)->nullable();
            $table->string('doi')->nullable();
            $table->string('url', 500)->nullable();
            $table->text('notes')->nullable();
            $table->json('sources')->nullable();
            $table->timestamp('checked_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('publications');
        Schema::table('users', fn (Blueprint $t) => $t->dropColumn('scholar_url'));
    }
};
