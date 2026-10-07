<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('market_markets', function (Blueprint $table) {
            // An editorial "not relevant to us", separate from is_active
            // (which tracks whether the market is still running). Set only
            // from the market page -- never by the form, an import or a
            // liveness score -- and cleared the same way.
            $table->timestamp('ignored_at')->nullable()->after('is_active');
            $table->string('ignored_reason')->nullable()->after('ignored_at');

            $table->index('ignored_at');
        });
    }

    public function down(): void
    {
        Schema::table('market_markets', function (Blueprint $table) {
            $table->dropIndex(['ignored_at']);
            $table->dropColumn(['ignored_at', 'ignored_reason']);
        });
    }
};
