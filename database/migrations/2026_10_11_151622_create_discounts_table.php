<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Enregistrer les réductions appliquées au montant total des frais d'un élève. */
    public function up(): void
    {
        Schema::create('discounts', function (Blueprint $table) {
            $table->bigIncrements('discount_id');
            $table->foreignId('student_id')->constrained('students', 'student_id')->cascadeOnDelete();
            $table->foreignId('tuition_fee_id')->constrained('tuition_fees', 'tuition_fee_id')->cascadeOnDelete();
            $table->decimal('amount', 12, 2);
            $table->string('reason', 255);
            $table->timestamps();
        });

        DB::statement('ALTER TABLE discounts ADD CONSTRAINT discounts_amount_check CHECK (amount > 0)');
    }

    public function down(): void
    {
        Schema::dropIfExists('discounts');
    }
};
