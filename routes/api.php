<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::post('/analyze', [App\Http\Controllers\ReadingAnalysisController::class, 'analyze']);
