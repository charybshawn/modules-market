<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * One row per update email run: the watermark `market:notify-updates`
     * reads to know what is new, and a record of what went out.
     */
    public function up(): void
    {
        Schema::create('market_update_digests', function (Blueprint $table) {
            $table->id();
            $table->timestamp('covers_through');
            $table->json('counts')->nullable();
            $table->unsignedInteger('recipients')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('market_update_digests');
    }
};
