<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->string('receipt_number', 32)->nullable()->after('payment_id');
        });

        DB::table('payments')->whereNull('receipt_number')->orderBy('payment_id')->chunkById(
            500,
            function ($payments): void {
                foreach ($payments as $payment) {
                    $year = substr($payment->payment_date, 0, 4);
                    $receiptNumber = 'SP-'.$year.'-'.str_pad((string) $payment->payment_id, 8, '0', STR_PAD_LEFT);

                    DB::table('payments')
                        ->where('payment_id', $payment->payment_id)
                        ->update(['receipt_number' => $receiptNumber]);
                }
            },
            'payment_id',
        );

        DB::statement('ALTER TABLE payments ALTER COLUMN receipt_number SET NOT NULL');

        Schema::table('payments', function (Blueprint $table) {
            $table->unique('receipt_number');
        });
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->dropUnique(['receipt_number']);
            $table->dropColumn('receipt_number');
        });
    }
};
