<?php

use App\Http\Controllers\BillingQueueController;
use App\Http\Controllers\BillingReviewController;
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

Route::middleware('auth')->group(function (): void {
    Route::get(
        '/billing',
        [BillingQueueController::class, 'index']
    )->name('billing.index');
});

Route::middleware('auth')->post(
    '/billing/{client}/draft',
    [BillingQueueController::class, 'createDraft']
)->name('billing.create-draft');

Route::middleware('auth')->post(
    '/billing/drafts/bulk',
    [BillingQueueController::class, 'createBulkDrafts']
)->name('billing.create-bulk-drafts');

Route::middleware('auth')->group(function (): void {
    Route::get(
        '/billing/review',
        [BillingReviewController::class, 'index']
    )->name('billing.review');

    Route::post(
        '/billing/review/send-approved',
        [BillingReviewController::class, 'sendApproved']
    )->name('billing.review.send-approved');

    Route::post(
        '/billing/review/{invoice}/approve',
        [BillingReviewController::class, 'approve']
    )->name('billing.review.approve');

    Route::post(
        '/billing/review/approve-bulk',
        [BillingReviewController::class, 'approveBulk']
    )->name('billing.review.approve-bulk');
});

Route::middleware('auth')->group(function (): void {
    Route::get(
        '/money-out',
        [\App\Http\Controllers\MoneyOutController::class, 'index']
    )->name('money-out.index');

    Route::post(
        '/money-out/categorise',
        [\App\Http\Controllers\MoneyOutController::class, 'categorise']
    )->name('money-out.categorise');

    Route::post(
        '/money-out/{row}/review',
        [\App\Http\Controllers\MoneyOutController::class, 'review']
    )->name('money-out.review');
});
