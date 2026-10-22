<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('classes', function (Blueprint $table) {
            $table->bigIncrements('class_id');
            $table->string('name', 100);
            $table->string('level', 50);
            $table->unsignedSmallInteger('capacity')->nullable();
            $table->foreignId('school_year_id')
                ->constrained('school_years', 'school_year_id')
                ->cascadeOnDelete();
            $table->unique(['class_id', 'school_year_id']);
            $table->unique(['name', 'school_year_id']);
            $table->timestamps();
        });

        DB::statement('ALTER TABLE classes ADD CONSTRAINT classes_capacity_check CHECK (capacity IS NULL OR capacity > 0)');
    }

    public function down(): void
    {
        Schema::dropIfExists('classes');
    }
};
