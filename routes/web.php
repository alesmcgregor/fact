<?php

use App\Http\Controllers\Api\AnalysisController;
use App\Http\Controllers\LanguageController;
use App\Http\Controllers\ReportController;
use App\Http\Middleware\SetLocale;
use App\Models\Country;
use Illuminate\Support\Facades\Route;

Route::middleware(SetLocale::class)->group(function (): void {
    Route::get('/', function () {
        $countries = Country::query()->where('active', true)->orderBy('code')->get();

        return view('home', ['countries' => $countries]);
    })->name('home');

    Route::post('/language', [LanguageController::class, 'update'])->name('language.update');

    Route::post('/api/analyses', [AnalysisController::class, 'store'])
        ->middleware('throttle:analysis-create')
        ->name('api.analyses.store');

    Route::get('/api/analyses/{analysis:public_id}', [AnalysisController::class, 'show'])
        ->middleware('throttle:analysis-read')
        ->name('api.analyses.show');

    Route::get('/r/{analysis:public_id}', [ReportController::class, 'show'])->name('reports.show');
});
