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
            // Split rather than one free-text line so this can eventually
            // be filled by Canada Post's AddressComplete widget (it
            // returns exactly this shape: a street line, an optional
            // unit/suite line, province, postal code -- city is already
            // its own column above, doubles as a filter/index field, so
            // isn't repeated here). province defaults to 'BC' in the
            // Create form since that's the overwhelming case for this
            // module, not enforced at the DB/validation level -- a market
            // just over the border is still just a string, not an error.
            $table->string('address_line1')->nullable();
            $table->string('address_line2')->nullable();
            $table->string('province')->nullable();
            $table->string('postal_code')->nullable();
            // Small controlled set -- see MarketController::validated()'s
            // Rule::in. frequency_detail (below) carries the free-text
            // elaboration regardless of which bucket this is.
            $table->string('frequency')->nullable();
            $table->text('frequency_detail')->nullable();
            $table->text('vendor_fees')->nullable();
            // Market's own general/public line -- manager_phone below is
            // the manager's own direct line, a different number in
            // practice more often than not.
            $table->string('phone')->nullable();
            $table->string('manager')->nullable();
            $table->string('manager_phone')->nullable();
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
            // 0-4, see Market::LIVENESS_LABELS -- how confident a check (the
            // find-bc-markets skill's research pass, or a manual admin
            // re-verify) is that this market is still actually running, not
            // just still sitting in an old directory listing.
            // liveness_checked_at is when that score was last determined,
            // so a stale 4/4 from over a year ago reads differently than a
            // fresh one -- null on both means never checked.
            $table->unsignedTinyInteger('liveness_score')->nullable();
            $table->date('liveness_checked_at')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index('city');
            $table->index('region');
            $table->index('market_type');
            $table->index('frequency');
            $table->index('liveness_score');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('market_markets');
    }
};
