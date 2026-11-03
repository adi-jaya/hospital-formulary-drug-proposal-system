<?php

use App\Http\Controllers\SuggestionController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::prefix('api/suggestions')->group(function (): void {
    Route::get('/medicines', [SuggestionController::class, 'medicines'])->name('suggestions.medicines');
    Route::get('/principals', [SuggestionController::class, 'principals'])->name('suggestions.principals');
});
