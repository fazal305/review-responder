<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->string('google_sub')->nullable()->unique();
            $table->enum('role', ['owner', 'admin'])->default('owner');
            $table->boolean('is_demo')->default(false);
            $table->string('timezone', 64)->default('UTC');
            $table->boolean('notify_daily')->default(true);
            $table->unsignedTinyInteger('notify_hour')->default(7);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('users');
    }
};
