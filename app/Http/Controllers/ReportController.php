<?php

namespace App\Http\Controllers;

use App\Models\Analysis;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\App;

class ReportController extends Controller
{
    public function show(Analysis $analysis): View
    {
        abort_if($analysis->expires_at?->isPast(), 404);
        App::setLocale($analysis->locale);

        if ($analysis->status->value === 'completed') {
            $analysis->load('claims.evidence');
        }

        return view('reports.show', ['analysis' => $analysis]);
    }
}
