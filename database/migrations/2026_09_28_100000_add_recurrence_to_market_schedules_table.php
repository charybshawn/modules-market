<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('market_schedules', function (Blueprint $table) {
            // Structured "which days, what hours" -- frequency_detail stays as
            // the human-readable note, these are what the calendar places.
            // Int array, 0 = Sunday ... 6 = Saturday.
            $table->json('weekdays')->nullable()->after('frequency_detail');
            // Only for frequency = monthly: 1-4 = Nth weekday of the month,
            // -1 = the last one.
            $table->tinyInteger('week_of_month')->nullable()->after('weekdays');
            $table->time('start_time')->nullable()->after('week_of_month');
            $table->time('end_time')->nullable()->after('start_time');
        });
    }

    public function down(): void
    {
        Schema::table('market_schedules', function (Blueprint $table) {
            $table->dropColumn(['weekdays', 'week_of_month', 'start_time', 'end_time']);
        });
    }
};
