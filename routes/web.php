<?php

use App\Http\Controllers\Auth\GoogleController;
use App\Http\Controllers\SessionExportController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return redirect('/admin');
});

// Google Authentication Routes
Route::get('/auth/google', [GoogleController::class, 'redirectToGoogle'])->name('auth.google');
Route::get('/auth/google/callback', [GoogleController::class, 'handleGoogleCallback'])->name('auth.google.callback');

// Session Export Routes (for Taildrop, Google Drive, or Local Save)
Route::middleware(['web', 'auth'])->group(function () {
    Route::get('/admin/sessions/export/{format?}', [SessionExportController::class, 'download'])
        ->name('admin.sessions.export');
});
