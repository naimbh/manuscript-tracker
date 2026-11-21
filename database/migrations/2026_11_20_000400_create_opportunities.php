<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->text('interests')->nullable()->after('calendar_token');
        });

        Schema::create('scans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('status')->default('running'); // running, done, failed
            $table->string('trigger')->default('manual'); // manual, daily
            $table->unsignedSmallInteger('candidates')->default(0);
            $table->unsignedSmallInteger('found')->default(0);
            $table->text('error')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();
        });

        Schema::create('opportunities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('scan_id')->nullable()->constrained()->nullOnDelete();
            $table->string('title');
            $table->string('kind')->default('conference'); // conference, special_issue, journal, workshop
            $table->string('venue')->nullable();
            $table->string('publisher')->nullable();
            $table->string('outlet')->nullable(); // proceedings or journal standing
            $table->date('deadline_on')->nullable();
            $table->string('event')->nullable();
            $table->string('url', 500);
            $table->string('url_hash', 64);
            $table->text('summary')->nullable();
            $table->text('why')->nullable();
            $table->unsignedTinyInteger('fit')->default(3);
            $table->string('status')->default('new'); // new, saved, dismissed
            $table->timestamps();
            $table->unique(['user_id', 'url_hash']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('opportunities');
        Schema::dropIfExists('scans');
        Schema::table('users', fn (Blueprint $table) => $table->dropColumn('interests'));
    }
};
