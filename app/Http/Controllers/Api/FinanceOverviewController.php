<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Finance\BuildFinanceOverview;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Fournir au tableau de bord les encaissements et retards d'une année scolaire. */
class FinanceOverviewController extends Controller
{
    public function __invoke(Request $request, BuildFinanceOverview $overview): JsonResponse
    {
        $filters = $request->validate([
            'school_year_id' => ['required', 'integer', 'exists:school_years,school_year_id'],
        ]);

        return response()->json(['data' => $overview->forSchoolYear($filters['school_year_id'])]);
    }
}
