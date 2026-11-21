<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('calendar_token', 64)->nullable()->unique()->after('remember_token');
        });

        Schema::create('venues', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('kind')->default('journal');
            $table->string('review_type')->default('double');
            $table->string('word_limit')->nullable();
            $table->unsignedSmallInteger('decision_days')->nullable();
            $table->string('fee')->nullable();
            $table->string('preprint_policy')->default('unknown');
            $table->string('portal_url')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('papers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->string('short_title')->nullable();
            $table->string('stage')->default('drafting');
            $table->text('abstract')->nullable();
            $table->string('method')->nullable();
            $table->string('dataset')->nullable();
            $table->string('keywords')->nullable();
            $table->string('overleaf_url')->nullable();
            $table->string('drive_url')->nullable();
            $table->string('preprint_server')->nullable();
            $table->string('preprint_url')->nullable();
            $table->date('preprint_on')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('submissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('paper_id')->constrained()->cascadeOnDelete();
            $table->foreignId('venue_id')->nullable()->constrained()->nullOnDelete();
            $table->string('venue_name');
            $table->string('manuscript_id')->nullable();
            $table->string('portal_url')->nullable();
            $table->date('submitted_on')->nullable();
            $table->string('decision')->default('pending');
            $table->date('decision_on')->nullable();
            $table->date('revision_due_on')->nullable();
            $table->text('note')->nullable();
            $table->timestamps();
        });

        Schema::create('queue_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('paper_id')->constrained()->cascadeOnDelete();
            $table->foreignId('venue_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('position')->default(0);
            $table->timestamps();
            $table->unique(['paper_id', 'venue_id']);
        });

        Schema::create('authors', function (Blueprint $table) {
            $table->id();
            $table->foreignId('paper_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('affiliation')->nullable();
            $table->string('email')->nullable();
            $table->string('orcid')->nullable();
            $table->boolean('corresponding')->default(false);
            $table->json('roles')->nullable();
            $table->unsignedSmallInteger('position')->default(0);
            $table->timestamps();
        });

        Schema::create('deadlines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('paper_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->date('due_on');
            $table->boolean('done')->default(false);
            $table->timestamps();
        });

        Schema::create('revision_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('submission_id')->constrained()->cascadeOnDelete();
            $table->text('comment');
            $table->text('response')->nullable();
            $table->boolean('done')->default(false);
            $table->unsignedSmallInteger('position')->default(0);
            $table->timestamps();
        });

        Schema::create('paper_files', function (Blueprint $table) {
            $table->id();
            $table->foreignId('paper_id')->constrained()->cascadeOnDelete();
            $table->foreignId('submission_id')->nullable()->constrained()->nullOnDelete();
            $table->string('kind')->default('manuscript');
            $table->string('label')->nullable();
            $table->string('path');
            $table->string('original_name');
            $table->string('mime')->nullable();
            $table->unsignedBigInteger('size')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        foreach (['paper_files', 'revision_items', 'deadlines', 'authors', 'queue_items', 'submissions', 'papers', 'venues'] as $t) {
            Schema::dropIfExists($t);
        }
        Schema::table('users', fn (Blueprint $table) => $table->dropColumn('calendar_token'));
    }
};
