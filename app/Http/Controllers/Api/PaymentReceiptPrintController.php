<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ParentAccount;
use App\Models\Payment;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class PaymentReceiptPrintController extends Controller
{
    /** Afficher un reçu de paiement prêt à imprimer ou à enregistrer en PDF. */
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

        return response()->view('finance.receipts.print', [
            'payment' => $payment,
            'schoolName' => config('app.school_name', 'ScolaPlus'),
            'isPdf' => false,
        ])->header('Content-Disposition', 'inline; filename="recu-'.$payment->receipt_number.'.html"');
    }
}
