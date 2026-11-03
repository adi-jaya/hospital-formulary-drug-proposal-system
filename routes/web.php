<?php

declare(strict_types=1);

use App\Http\Controllers\ProposalController;
use App\Http\Controllers\ProposalRecapController;
use App\Http\Controllers\SuggestionController;
use Illuminate\Support\Facades\Route;

Route::get('/', [ProposalController::class, 'create'])->name('proposals.create');
Route::post('/proposals', [ProposalController::class, 'store'])->middleware('throttle:30,1')->name('proposals.store');

Route::get('/recap', [ProposalRecapController::class, 'index'])->name('proposals.index');

Route::prefix('api/suggestions')->group(function (): void {
    Route::get('/medicines', [SuggestionController::class, 'medicines'])->name('suggestions.medicines');
    Route::get('/principals', [SuggestionController::class, 'principals'])->name('suggestions.principals');
});
