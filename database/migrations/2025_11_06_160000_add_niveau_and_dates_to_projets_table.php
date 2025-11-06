<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('projets', function (Blueprint $table) {
            $table->json('niveaux')->nullable()->after('description'); // Stocker les niveaux sous forme de JSON (ex: ["L1", "L2"])
            $table->date('date_debut')->nullable()->after('niveaux');
            $table->date('date_fin')->nullable()->after('date_debut');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('projets', function (Blueprint $table) {
            $table->dropColumn(['niveaux', 'date_debut', 'date_fin']);
        });
    }
};

