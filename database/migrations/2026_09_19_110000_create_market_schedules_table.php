<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('market_schedules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('market_id')->constrained('market_markets')->cascadeOnDelete();
            // "Summer Market", "Winter Market", "Christmas Craft Fair" --
            // optional, a market with one plain schedule doesn't need a name.
            $table->string('label')->nullable();
            // Same controlled set as Market::FREQUENCIES; frequency_detail
            // carries the actual days/hours as free text.
            $table->string('frequency')->nullable();
            $table->text('frequency_detail')->nullable();
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();
            // Only set when this schedule is held somewhere other than the
            // market's own address (e.g. a holiday fair at a mall).
            $table->string('address_line1')->nullable();
            $table->text('notes')->nullable();
            // Required, unlike the market-level score: each schedule has to
            // say whether *it* is current (a market can be alive while its
            // winter schedule quietly isn't). 0-4, Market::LIVENESS_LABELS.
            $table->unsignedTinyInteger('liveness_score');
            $table->date('liveness_checked_at');
            $table->timestamps();

            $table->index('frequency');
            $table->index('liveness_score');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('market_schedules');
    }
};
