<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Relier chaque classe à son enseignant titulaire, lorsqu'il est désigné. */
    public function up(): void
    {
        Schema::table('classes', function (Blueprint $table) {
            $table->foreignId('titular_teacher_id')
                ->nullable()
                ->constrained('teachers', 'teacher_id')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('classes', function (Blueprint $table) {
            $table->dropConstrainedForeignId('titular_teacher_id');
        });
    }
};
