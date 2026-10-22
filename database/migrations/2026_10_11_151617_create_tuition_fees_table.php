<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Définir les frais totaux dus pour une classe et une année scolaire. */
    public function up(): void
    {
        Schema::create('tuition_fees', function (Blueprint $table) {
            $table->bigIncrements('tuition_fee_id');
            $table->unsignedBigInteger('class_id');
            $table->foreignId('school_year_id')->constrained('school_years', 'school_year_id')->cascadeOnDelete();
            $table->decimal('total_amount', 12, 2);
            $table->foreign(['class_id', 'school_year_id'])
                ->references(['class_id', 'school_year_id'])
                ->on('classes')
                ->cascadeOnDelete();
            $table->unique(['class_id', 'school_year_id']);
            $table->timestamps();
        });

        DB::statement('ALTER TABLE tuition_fees ADD CONSTRAINT tuition_fees_total_amount_check CHECK (total_amount > 0)');
    }

    public function down(): void
    {
        Schema::dropIfExists('tuition_fees');
    }
};
