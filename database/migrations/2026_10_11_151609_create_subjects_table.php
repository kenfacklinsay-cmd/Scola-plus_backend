<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Créer le catalogue des matières et leur coefficient de calcul. */
    public function up(): void
    {
        Schema::create('subjects', function (Blueprint $table) {
            $table->bigIncrements('subject_id');
            $table->string('name', 100)->unique();
            $table->unsignedSmallInteger('coefficient');
            $table->timestamps();
        });

        DB::statement('ALTER TABLE subjects ADD CONSTRAINT subjects_coefficient_check CHECK (coefficient > 0)');
    }

    public function down(): void
    {
        Schema::dropIfExists('subjects');
    }
};
