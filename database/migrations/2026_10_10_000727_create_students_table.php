<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Créer la table des élèves.
     */
    public function up(): void
    {
        Schema::create('students', function (Blueprint $table) {
            $table->bigIncrements('student_id');

            $table->string('matricule', 30)->unique();

            $table->string('last_name', 100);

            $table->string('first_name', 100);

            $table->date('birth_date');

            $table->string('birth_place', 150);

            $table->string('gender', 20);

            $table->text('address');

            $table->string('photo', 255)->nullable();

            $table->timestamps();
        });
    }

    /**
     * Supprimer la table lors du rollback.
     */
    public function down(): void
    {
        Schema::dropIfExists('students');
    }
};
