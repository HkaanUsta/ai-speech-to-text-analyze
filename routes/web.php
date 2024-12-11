<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::post('/analyze', [App\Http\Controllers\ReadingAnalysisController::class, 'analyze']);








