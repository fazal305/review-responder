<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('business_guidelines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->enum('tone', ['friendly', 'professional', 'formal', 'casual'])->default('friendly');
            $table->enum('strictness', ['relaxed', 'balanced', 'strict'])->default('balanced');
            $table->string('sign_off', 120)->nullable();
            $table->text('extra_instructions')->nullable();
            $table->text('banned_phrases')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('business_guidelines');
    }
};
