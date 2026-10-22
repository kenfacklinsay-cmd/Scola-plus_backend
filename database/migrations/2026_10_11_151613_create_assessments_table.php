<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Créer les évaluations qui recevront les notes des élèves. */
    public function up(): void
    {
        Schema::create('assessments', function (Blueprint $table) {
            $table->bigIncrements('assessment_id');
            $table->foreignId('assignment_id')->constrained('assignments', 'assignment_id')->cascadeOnDelete();
            $table->foreignId('period_id')->constrained('periods', 'period_id')->cascadeOnDelete();
            $table->string('label', 100);
            $table->date('assessment_date');
            $table->unique(['assignment_id', 'period_id', 'label']);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('assessments');
    }
};
