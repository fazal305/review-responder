<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('drafts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('review_id')->constrained()->cascadeOnDelete();
            $table->enum('status', ['pending_review', 'published', 'dismissed', 'draft_failed'])->default('pending_review')->index();
            $table->text('body');
            $table->string('tone_modifier', 40)->nullable();
            $table->string('generator');
            $table->string('error_code', 64)->nullable();
            $table->timestamp('published_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('drafts');
    }
};
