<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->date('last_summary_sent_on')->nullable()->after('notify_hour');
            $table->timestamp('terms_accepted_at')->nullable()->after('last_summary_sent_on');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['last_summary_sent_on', 'terms_accepted_at']);
        });
    }
};
