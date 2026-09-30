<?php

use Cultpantry\Market\Models\Market;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Nechako, North Coast and Northern Rockies became one "Northern BC".
     */
    public function up(): void
    {
        foreach (Market::RETIRED_REGIONS as $old => $new) {
            DB::table('market_markets')->where('region', $old)->update(['region' => $new]);
        }
    }

    /**
     * The three regions can't be told apart again once merged, so there is
     * nothing to put back.
     */
    public function down(): void {}
};
