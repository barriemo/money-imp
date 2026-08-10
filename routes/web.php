<?php

use App\Http\Controllers\Integrations\FreeAgentController;
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
