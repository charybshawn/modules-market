<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('market_markets', function (Blueprint $table) {
            // The business or organization behind a market when that isn't
            // its name -- "Winter at Finz Night Market" is sponsored by "Finz
            // Restaurant". Added as its own migration (not folded into the
            // create-table one) because the table holds real data by now.
            $table->string('sponsor')->nullable()->after('market_type');
        });
    }

    public function down(): void
    {
        Schema::table('market_markets', function (Blueprint $table) {
            $table->dropColumn('sponsor');
        });
    }
};
