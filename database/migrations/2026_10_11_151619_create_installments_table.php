<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Découper les frais de scolarité en tranches avec échéance. */
    public function up(): void
    {
        Schema::create('installments', function (Blueprint $table) {
            $table->bigIncrements('installment_id');
            $table->foreignId('tuition_fee_id')->constrained('tuition_fees', 'tuition_fee_id')->cascadeOnDelete();
            $table->string('label', 80);
            $table->decimal('amount', 12, 2);
            $table->date('due_date');
            $table->timestamps();
            $table->unique(['tuition_fee_id', 'label']);
        });

        DB::statement('ALTER TABLE installments ADD CONSTRAINT installments_amount_check CHECK (amount > 0)');
    }

    public function down(): void
    {
        Schema::dropIfExists('installments');
    }
};
