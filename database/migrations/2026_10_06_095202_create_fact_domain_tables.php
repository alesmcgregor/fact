<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('countries', function (Blueprint $table): void {
            $table->id();
            $table->char('code', 2)->unique();
            $table->string('name_key', 80);
            $table->string('flag', 8);
            $table->boolean('active')->default(true);
            $table->timestamps();
        });

        Schema::create('institutions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('country_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('type', 40);
            $table->unsignedSmallInteger('priority')->default(100);
            $table->boolean('active')->default(true);
            $table->timestamps();
            $table->index(['country_id', 'active', 'priority']);
        });

        Schema::create('official_sources', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('institution_id')->constrained()->cascadeOnDelete();
            $table->string('label');
            $table->string('url', 2048)->unique();
            $table->string('domain', 255);
            $table->string('language', 8)->nullable();
            $table->string('verification_status', 30)->default('verified');
            $table->unsignedSmallInteger('priority')->default(100);
            $table->boolean('active')->default(true);
            $table->timestamp('last_verified_at')->nullable();
            $table->timestamps();
            $table->index(['institution_id', 'active', 'priority']);
        });

        Schema::create('analyses', function (Blueprint $table): void {
            $table->id();
            $table->string('public_id', 40)->unique();
            $table->string('submitted_url', 2048);
            $table->string('normalized_url', 2048)->nullable();
            $table->string('locale', 8)->default('en');
            $table->string('status', 20)->default('queued');
            $table->string('stage', 40)->default('queued');
            $table->string('failure_code', 80)->nullable();
            $table->string('content_title', 500)->nullable();
            $table->text('content_excerpt')->nullable();
            $table->string('content_language', 12)->nullable();
            $table->text('summary')->nullable();
            $table->string('verification_status', 40)->nullable();
            $table->unsignedTinyInteger('evidence_score')->nullable();
            $table->string('manipulation_level', 16)->nullable();
            $table->json('manipulation_signals')->nullable();
            $table->json('limitations')->nullable();
            $table->boolean('demo_mode')->default(false);
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('expires_at')->index();
            $table->timestamps();
            $table->index(['status', 'created_at']);
        });

        Schema::create('claims', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('analysis_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('position');
            $table->text('statement');
            $table->string('classification', 24)->default('factual');
            $table->string('verification_status', 40)->default('insufficient_evidence');
            $table->text('explanation')->nullable();
            $table->unsignedTinyInteger('evidence_score')->default(0);
            $table->unsignedSmallInteger('supporting_official_count')->default(0);
            $table->unsignedSmallInteger('supporting_independent_count')->default(0);
            $table->unsignedSmallInteger('contradicting_count')->default(0);
            $table->timestamps();
            $table->unique(['analysis_id', 'position']);
        });

        Schema::create('evidence', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('analysis_id')->constrained()->cascadeOnDelete();
            $table->foreignId('claim_id')->nullable()->constrained()->nullOnDelete();
            $table->string('title', 500);
            $table->string('publisher', 255)->nullable();
            $table->string('url', 2048);
            $table->string('source_type', 24);
            $table->string('relationship', 24)->default('context');
            $table->text('summary')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->timestamp('retrieved_at');
            $table->timestamps();
            $table->index(['analysis_id', 'source_type']);
            $table->index(['claim_id', 'relationship']);
        });

        Schema::create('analysis_events', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('analysis_id')->constrained()->cascadeOnDelete();
            $table->string('stage', 40);
            $table->string('state', 20)->default('complete');
            $table->json('details')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->index(['analysis_id', 'id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('analysis_events');
        Schema::dropIfExists('evidence');
        Schema::dropIfExists('claims');
        Schema::dropIfExists('analyses');
        Schema::dropIfExists('official_sources');
        Schema::dropIfExists('institutions');
        Schema::dropIfExists('countries');
    }
};
