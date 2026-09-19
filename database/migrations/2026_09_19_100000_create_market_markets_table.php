<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('market_markets', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('city')->nullable();
            // Informal named area (e.g. "Shuswap", "Thompson Okanagan") --
            // not a fixed enum, these aren't official administrative
            // boundaries, just how BC markets get grouped colloquially.
            $table->string('region')->nullable();
            // Free text, autocomplete-suggested in the UI -- "Farmers",
            // "Artisan", "Makers", etc. Deliberately open, not an enum.
            $table->string('market_type')->nullable();
            $table->string('address')->nullable();
            // Small controlled set -- see MarketController::validated()'s
            // Rule::in. frequency_detail (below) carries the free-text
            // elaboration regardless of which bucket this is.
            $table->string('frequency')->nullable();
            $table->text('frequency_detail')->nullable();
            $table->text('vendor_fees')->nullable();
            $table->string('phone')->nullable();
            $table->string('manager')->nullable();
            $table->string('manager_email')->nullable();
            $table->string('facebook_page')->nullable();
            $table->string('instagram_page')->nullable();
            $table->string('website')->nullable();
            $table->text('description')->nullable();
            $table->text('notes')->nullable();
            // One or more provenance URLs/notes, free text (one per line) --
            // not a separate table for v1; revisit if per-source structure
            // (timestamps, per-source status) is ever needed.
            $table->text('sources')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index('city');
            $table->index('region');
            $table->index('market_type');
            $table->index('frequency');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('market_markets');
    }
};
