<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\Students\ParentResource;
use App\Models\ParentAccount;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/** Lister les parents et les élèves associés pour la gestion de l'établissement. */
class ParentController extends Controller
{
    public function index(): AnonymousResourceCollection
    {
        return ParentResource::collection(
            ParentAccount::query()
                ->with(['students', 'user'])
                ->orderBy('last_name')
                ->orderBy('first_name')
                ->get(),
        );
    }
}
