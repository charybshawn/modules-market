<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The ledger of research files `market:import-pending` has loaded,
     * keyed by content hash so an edited file is imported again.
     */
    public function up(): void
    {
        Schema::create('market_imports', function (Blueprint $table) {
            $table->id();
            $table->string('filename');
            $table->string('hash', 64)->unique();
            $table->text('summary');
            $table->boolean('marked_only')->default(false);
            $table->timestamp('imported_at');
            $table->timestamps();

            $table->index('filename');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('market_imports');
    }
};
