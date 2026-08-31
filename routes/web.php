<?php

use App\Http\Controllers\BillingQueueController;
use App\Http\Controllers\BillingReviewController;
use App\Http\Controllers\ChaseQueueController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DebtorController;
use App\Http\Controllers\ExecutiveActionController;
use App\Http\Controllers\FinancialTruthController;
use App\Http\Controllers\ImportInboxController;
use App\Http\Controllers\Integrations\FreeAgentController;
use App\Http\Controllers\MoneyOutController;
use App\Http\Controllers\MoneyOutImportController;
use App\Http\Controllers\ReconciliationInboxController;
use App\Http\Controllers\SupplierAssetController;
use App\Http\Controllers\SupplierPaymentReconciliationController;
use App\Http\Controllers\SupplierAttributionRuleController;
use App\Http\Controllers\SupplierController;
use App\Http\Controllers\SupplierTransactionController;
use App\Http\Controllers\UniversalImportController;
use App\Http\Controllers\WorkLogController;
use App\Http\Controllers\WorkReviewController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth')->group(function (): void {
    Route::get(
        '/executive-actions',
        [ExecutiveActionController::class, 'index']
    )->name('executive-actions.index');

    Route::get(
        '/executive-actions/{action}',
        [ExecutiveActionController::class, 'show']
    )->name('executive-actions.show');
});

Route::middleware('auth')->get(
    '/dashboard',
    [DashboardController::class, 'index']
)->name('dashboard');

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
        [MoneyOutController::class, 'index']
    )->name('money-out.index');

    Route::post(
        '/money-out/categorise',
        [MoneyOutController::class, 'categorise']
    )->name('money-out.categorise');

    Route::post(
        '/money-out/{row}/review',
        [MoneyOutController::class, 'review']
    )->name('money-out.review');
});

Route::middleware('auth')->group(function (): void {
    Route::get(
        '/money-out/import',
        [MoneyOutImportController::class, 'index']
    )->name('money-out.import.index');

    Route::post(
        '/money-out/import/preview',
        [MoneyOutImportController::class, 'preview']
    )->name('money-out.import.preview');

    Route::post(
        '/money-out/import/confirm',
        [MoneyOutImportController::class, 'import']
    )->name('money-out.import.confirm');

    Route::post(
        '/money-out/import/cancel',
        [MoneyOutImportController::class, 'cancel']
    )->name('money-out.import.cancel');
});

Route::middleware('auth')->get(
    '/imports',
    [ImportInboxController::class, 'index']
)->name('imports.index');

Route::middleware('auth')->group(function (): void {
    Route::get(
        '/work-log',
        [WorkLogController::class, 'index']
    )->name('work-log.index');

    Route::post(
        '/work-log',
        [WorkLogController::class, 'store']
    )->name('work-log.store');
});

Route::middleware('auth')->group(function (): void {
    Route::get(
        '/work-review',
        [WorkReviewController::class, 'index']
    )->name('work-review.index');

    Route::post(
        '/work-review/{workLog}',
        [WorkReviewController::class, 'update']
    )->name('work-review.update');
});

Route::middleware('auth')->post(
    '/work-review/client/{client}/invoice-draft',
    [WorkReviewController::class, 'createInvoiceDraft']
)->name('work-review.invoice-draft');

Route::middleware('auth')->post(
    '/imports/drop',
    [UniversalImportController::class, 'store']
)->name('imports.drop');

Route::middleware('auth')->post(
    '/imports/process-statements',
    [
        UniversalImportController::class,
        'processStatements',
    ]
)->name('imports.process-statements');

Route::middleware('auth')->post(
    '/imports/{batch}/process-supplier-invoice',
    [
        UniversalImportController::class,
        'processSupplierInvoice',
    ]
)->name('imports.process-supplier-invoice');

Route::middleware('auth')->group(function (): void {
    Route::get(
        '/supplier-payments',
        [SupplierPaymentReconciliationController::class, 'index']
    )->name('supplier-payments.index');

    Route::post(
        '/supplier-payments/generate',
        [SupplierPaymentReconciliationController::class, 'generate']
    )->name('supplier-payments.generate');

    Route::post(
        '/supplier-payments/{allocation}/approve',
        [SupplierPaymentReconciliationController::class, 'approve']
    )->name('supplier-payments.approve');

    Route::post(
        '/supplier-payments/{allocation}/reject',
        [SupplierPaymentReconciliationController::class, 'reject']
    )->name('supplier-payments.reject');
});

Route::middleware('auth')->get(
    '/financial-truth',
    [
        FinancialTruthController::class,
        'index',
    ]
)->name('financial-truth.index');

Route::middleware('auth')->post(
    '/financial-truth/balance',
    [
        FinancialTruthController::class,
        'storeBalance',
    ]
)->name('financial-truth.balance.store');

Route::middleware('auth')->post(
    '/financial-truth/liability',
    [
        FinancialTruthController::class,
        'storeLiability',
    ]
)->name('financial-truth.liability.store');

Route::middleware('auth')->get(
    '/suppliers',
    [
        SupplierController::class,
        'index',
    ]
)->name('suppliers.index');

Route::middleware('auth')->post(
    '/suppliers',
    [
        SupplierController::class,
        'store',
    ]
)->name('suppliers.store');

Route::middleware('auth')->get(
    '/suppliers/{supplier}/transactions',
    [
        SupplierTransactionController::class,
        'index',
    ]
)->name('suppliers.transactions.index');

Route::middleware('auth')->post(
    '/suppliers/{supplier}/transactions/{transaction}',
    [
        SupplierTransactionController::class,
        'update',
    ]
)->name('suppliers.transactions.update');

Route::middleware('auth')->get(
    '/suppliers/rules',
    [
        SupplierAttributionRuleController::class,
        'index',
    ]
)->name('suppliers.rules.index');

Route::middleware('auth')->post(
    '/suppliers/rules/{rule}/toggle',
    [
        SupplierAttributionRuleController::class,
        'toggle',
    ]
)->name('suppliers.rules.toggle');

Route::middleware('auth')->delete(
    '/suppliers/rules/{rule}',
    [
        SupplierAttributionRuleController::class,
        'destroy',
    ]
)->name('suppliers.rules.destroy');

Route::middleware('auth')->get(
    '/suppliers/assets',
    [
        SupplierAssetController::class,
        'index',
    ]
)->name('suppliers.assets.index');

Route::middleware('auth')->post(
    '/suppliers/assets/{asset}',
    [
        SupplierAssetController::class,
        'update',
    ]
)->name('suppliers.assets.update');
