<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('analyses', function (Blueprint $table): void {
            $table->string('request_fingerprint', 64)->nullable()->index();
            $table->boolean('include_perspectives')->default(false);
            $table->json('content_metadata')->nullable();
            $table->json('research_context')->nullable();
            $table->text('supplied_transcript')->nullable();
        });
        Schema::table('evidence', function (Blueprint $table): void {
            $table->string('identity_url', 2048)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('evidence', fn (Blueprint $table) => $table->dropColumn('identity_url'));
        Schema::table('analyses', function (Blueprint $table): void {
            $table->dropIndex(['request_fingerprint']);
            $table->dropColumn(['request_fingerprint', 'include_perspectives', 'content_metadata', 'research_context', 'supplied_transcript']);
        });
    }
};
