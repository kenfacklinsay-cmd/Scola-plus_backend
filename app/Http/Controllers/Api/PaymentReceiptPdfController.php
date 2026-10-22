<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ParentAccount;
use App\Models\Payment;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Str;

/** Générer le fichier PDF du reçu d'un paiement enregistré. */
class PaymentReceiptPdfController extends Controller
{
    public function __invoke(Request $request, Payment $payment): Response
    {
        if ($request->user()->role === 'PARENT') {
            $parent = ParentAccount::query()->where('user_id', $request->user()->getKey())->firstOrFail();
            abort_unless(
                $parent->students()->where('students.student_id', $payment->student_id)->exists(),
                404,
            );
        }

        $payment->loadMissing([
            'student',
            'installment.tuitionFee.schoolClass',
            'installment.tuitionFee.schoolYear',
        ]);

        return Pdf::loadView('finance.receipts.print', [
            'payment' => $payment,
            'schoolName' => config('app.school_name', 'ScolaPlus'),
            'isPdf' => true,
        ])->setPaper('a4')->download('recu-'.Str::slug($payment->receipt_number).'.pdf');
    }
}
