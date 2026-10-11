<?php

use Cultpantry\Market\Models\Market;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * The free-text market types become the eight in Market::MARKET_TYPES.
     * A value that maps to none of them is cleared, so it shows up as "No
     * type set" and gets picked by hand.
     */
    public function up(): void
    {
        $stored = DB::table('market_markets')->whereNotNull('market_type')->distinct()->pluck('market_type');

        foreach ($stored as $value) {
            $type = Market::canonicalMarketType($value);
            if ($type !== $value) {
                DB::table('market_markets')->where('market_type', $value)->update(['market_type' => $type ?: null]);
            }
        }
    }

    /**
     * Merged types can't be told apart again, so there is nothing to put back.
     */
    public function down(): void {}
};
