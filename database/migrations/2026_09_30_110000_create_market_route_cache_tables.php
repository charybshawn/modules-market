<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A cache of routing answers, so each pair is asked of the routing service
     * once. Nothing here is edited by hand and it can be emptied at any time;
     * it just refills as people search.
     */
    public function up(): void
    {
        Schema::create('market_drive_times', function (Blueprint $table) {
            $table->id();
            // Where the drive starts: "town:sorrento" or "postal:V1E4N2".
            $table->string('origin_key', 80);
            // The market's town, in Places' normalized spelling ("salmon arm").
            $table->string('destination_key', 120);
            // Null when the service found no drivable route between them.
            $table->unsignedSmallInteger('minutes')->nullable();
            $table->decimal('km', 7, 1)->nullable();
            $table->timestamp('fetched_at');

            $table->unique(['origin_key', 'destination_key']);
        });

        // A postal code someone searched from, located once.
        Schema::create('market_origins', function (Blueprint $table) {
            $table->id();
            $table->string('origin_key', 80)->unique();
            $table->string('label', 120);
            $table->decimal('latitude', 8, 5);
            $table->decimal('longitude', 8, 5);
            $table->timestamp('fetched_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('market_origins');
        Schema::dropIfExists('market_drive_times');
    }
};
