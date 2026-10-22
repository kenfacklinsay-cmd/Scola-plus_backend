<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Définir les périodes de notation d'une année scolaire. */
    public function up(): void
    {
        Schema::create('periods', function (Blueprint $table) {
            $table->bigIncrements('period_id');
            $table->string('label', 80);
            $table->date('start_date');
            $table->date('end_date');
            $table->foreignId('school_year_id')->constrained('school_years', 'school_year_id')->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['label', 'school_year_id']);
        });

        DB::statement('ALTER TABLE periods ADD CONSTRAINT periods_dates_check CHECK (end_date >= start_date)');
    }

    public function down(): void
    {
        Schema::dropIfExists('periods');
    }
};
