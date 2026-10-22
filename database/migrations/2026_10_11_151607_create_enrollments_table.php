<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Enregistrer l'inscription d'un élève dans une classe pour une année scolaire. */
    public function up(): void
    {
        Schema::create('enrollments', function (Blueprint $table) {
            $table->bigIncrements('enrollment_id');
            $table->foreignId('student_id')->constrained('students', 'student_id')->cascadeOnDelete();
            $table->unsignedBigInteger('class_id');
            $table->foreignId('school_year_id')->constrained('school_years', 'school_year_id')->cascadeOnDelete();
            $table->date('enrollment_date');
            $table->string('status', 20)->default('ACTIVE');
            $table->foreign(['class_id', 'school_year_id'])
                ->references(['class_id', 'school_year_id'])
                ->on('classes')
                ->cascadeOnDelete();

            // Un élève ne peut avoir qu'une inscription par année scolaire.
            $table->unique(['student_id', 'school_year_id']);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('enrollments');
    }
};
