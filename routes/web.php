<?php

use App\Http\Controllers\ChaseQueueController;
use App\Http\Controllers\DebtorController;
use App\Http\Controllers\Integrations\FreeAgentController;
use App\Http\Controllers\ReconciliationInboxController;
use Illuminate\Support\Facades\Route;

Route::get('/dashboard', function () {
    return view('dashboard');
})->middleware('auth')->name('dashboard');

Route::get('/', function () {
    return view('welcome');
});

Route::middleware('auth')->group(function (): void {
    Route::get(
        '/integrations/freeagent/connect',
        [FreeAgentController::class, 'connect']
    )->name('integrations.freeagent.connect');

    Route::get(
        '/integrations/freeagent/callback',
        [FreeAgentController::class, 'callback']
    )->name('integrations.freeagent.callback');

    Route::get(
        '/integrations/freeagent/health',
        [FreeAgentController::class, 'health']
    )->name('integrations.freeagent.health');
});

Route::middleware('auth')->group(function (): void {
    Route::get(
        '/reconciliation',
        [ReconciliationInboxController::class, 'index']
    )->name('reconciliation.index');

    Route::post(
        '/reconciliation/{transaction}/client',
        [ReconciliationInboxController::class, 'assignClient']
    )->name('reconciliation.assign-client');

    Route::post(
        '/reconciliation/{transaction}/allocate',
        [ReconciliationInboxController::class, 'allocateInvoice']
    )->name('reconciliation.allocate-invoice');

    Route::post(
        '/reconciliation/{transaction}/ignore',
        [ReconciliationInboxController::class, 'ignore']
    )->name('reconciliation.ignore');
});

Route::middleware('auth')->group(function (): void {
    Route::get(
        '/debtors',
        [DebtorController::class, 'index']
    )->name('debtors.index');
});

Route::middleware('auth')->group(function (): void {
    Route::get(
        '/chase',
        [ChaseQueueController::class, 'index']
    )->name('chase.index');
});
