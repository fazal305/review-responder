<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reviews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('location_id')->constrained()->cascadeOnDelete();
            $table->string('google_review_name')->unique();
            $table->string('reviewer_display_name');
            $table->unsignedTinyInteger('star_rating');
            $table->text('comment')->nullable();
            $table->timestamp('reviewed_at');
            $table->enum('sentiment', ['positive', 'neutral', 'negative'])->nullable();
            $table->json('themes')->nullable();
            $table->text('existing_reply')->nullable();
            $table->timestamps();

            $table->index(['location_id', 'reviewed_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reviews');
    }
};
