<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('locations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('google_location_name');
            $table->string('title');
            $table->string('address_line')->nullable();
            $table->string('timezone', 64)->default('UTC');
            $table->boolean('monitoring')->default(false)->index();
            $table->date('last_synced_on')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'google_location_name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('locations');
    }
};
