<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A schedule's own type, for a run that's a different kind of market from
     * its host -- a farmers' market's Christmas market. Null means the
     * market's own type. Schedules already labelled as a holiday run on a
     * market that isn't a Holiday market are typed as one.
     */
    public function up(): void
    {
        Schema::table('market_schedules', function (Blueprint $table) {
            $table->string('market_type')->nullable()->after('label');
            $table->index('market_type');
        });

        DB::table('market_schedules')
            ->join('market_markets', 'market_markets.id', '=', 'market_schedules.market_id')
            ->where(fn ($q) => $q->whereNull('market_markets.market_type')->orWhere('market_markets.market_type', '!=', 'Holiday market'))
            ->whereNotNull('market_schedules.label')
            ->select('market_schedules.id', 'market_schedules.label')
            ->get()
            ->filter(fn ($row) => preg_match('/christmas|holiday|xmas|yule|festive|santa/i', $row->label) === 1)
            ->each(fn ($row) => DB::table('market_schedules')->where('id', $row->id)->update(['market_type' => 'Holiday market']));
    }

    public function down(): void
    {
        Schema::table('market_schedules', function (Blueprint $table) {
            $table->dropIndex(['market_type']);
            $table->dropColumn('market_type');
        });
    }
};
