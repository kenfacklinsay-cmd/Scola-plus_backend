<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Créer la table des années scolaires.
     */
    public function up(): void
    {
        Schema::create('school_years', function (Blueprint $table) {
            $table->bigIncrements('school_year_id');

            $table->string('label', 20)->unique();

            $table->date('start_date');

            $table->date('end_date');

            $table->string('status', 20)->default('UPCOMING');

            $table->timestamps();
        });

        // Vérifier les valeurs autorisées pour le statut.
        DB::statement("
            ALTER TABLE school_years
            ADD CONSTRAINT school_years_status_check
            CHECK (status IN ('UPCOMING', 'ONGOING', 'COMPLETED'))
        ");

        // La date de fin doit être postérieure ou égale à la date de début.
        DB::statement('
            ALTER TABLE school_years
            ADD CONSTRAINT school_years_dates_check
            CHECK (end_date >= start_date)
        ');
    }

    /**
     * Supprimer la table en cas de rollback.
     */
    public function down(): void
    {
        Schema::dropIfExists('school_years');
    }
};
