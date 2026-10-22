<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Stocker une note par élève et par évaluation ; une note absente reste non enregistrée. */
    public function up(): void
    {
        Schema::create('grades', function (Blueprint $table) {
            $table->bigIncrements('grade_id');
            $table->foreignId('student_id')->constrained('students', 'student_id')->cascadeOnDelete();
            $table->foreignId('assessment_id')->constrained('assessments', 'assessment_id')->cascadeOnDelete();
            $table->decimal('value', 4, 2);
            $table->text('appreciation')->nullable();
            $table->unique(['student_id', 'assessment_id']);
            $table->timestamps();
        });

        // La contrainte garantit l'échelle de notation /20 même hors validation applicative.
        DB::statement('ALTER TABLE grades ADD CONSTRAINT grades_value_check CHECK (value >= 0 AND value <= 20)');
    }

    public function down(): void
    {
        Schema::dropIfExists('grades');
    }
};
