<?php

use A2ZWeb\Affiliate\Http\Controllers\ApplyController;
use A2ZWeb\Affiliate\Http\Controllers\PartnerDashboardController;
use A2ZWeb\Affiliate\Http\Controllers\PayoutRequestController;
use A2ZWeb\Affiliate\Http\Controllers\StatementController;
use Illuminate\Support\Facades\Route;

Route::get('/', [PartnerDashboardController::class, 'index'])->name('dashboard');

Route::get('/apply', [ApplyController::class, 'show'])->name('apply.show');
Route::post('/apply', [ApplyController::class, 'store'])->name('apply.store');

Route::patch('/payout-details', [ApplyController::class, 'updatePayoutDetails'])->name('payout-details.update');

Route::post('/payouts', [PayoutRequestController::class, 'store'])->name('payouts.store');
Route::delete('/payouts/{payoutRequest}', [PayoutRequestController::class, 'cancel'])
    ->scopeBindings()
    ->name('payouts.cancel');

Route::get('/statements/{statement}', [StatementController::class, 'show'])->name('statements.show');
Route::get('/statements/{statement}/download', [StatementController::class, 'download'])->name('statements.download');
