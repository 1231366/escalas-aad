<?php

namespace App\Http\Controllers;

use App\Services\ViabilityCheck;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function index(Request $request, ViabilityCheck $viabilityCheck): Response
    {
        $isAdmin = (bool) $request->user()?->isAdmin();

        return Inertia::render('dashboard', [
            'viability' => $isAdmin ? $viabilityCheck->analyze() : null,
        ]);
    }
}
