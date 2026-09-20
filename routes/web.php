<?php

use App\Http\Controllers\AdminUserController;
use App\Http\Controllers\AiChatController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\PersonController;
use App\Http\Controllers\ProfileController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    // Deliberately no framework or runtime versions: they are not the visitor's
    // business and only help someone fingerprint the stack.
    return Inertia::render('Welcome', [
        'canLogin' => Route::has('login'),
        'canRegister' => Route::has('register'),
    ]);
});

Route::middleware(['auth', 'verified'])->group(function () {
    // The rolodex is the whole app; keep /dashboard as a polite alias.
    Route::get('/dashboard', fn () => redirect()->route('people.index'))->name('dashboard');

    Route::resource('people', PersonController::class);
    Route::resource('categories', CategoryController::class)->except('show');

    // Quick edit from the list: JSON in, JSON out, list stays put. The save is a
    // POST because PHP only parses multipart bodies for POST, and the popup can
    // carry a photo.
    Route::get('/people/{person}/quick-edit', [PersonController::class, 'quickEdit'])->name('people.quick-edit');
    Route::post('/people/{person}/quick-update', [PersonController::class, 'quickUpdate'])->name('people.quick-update');

    Route::post('/people/bulk-answers', [PersonController::class, 'bulkAnswers'])->name('people.bulk-answers');

    Route::post('/ai/chat', [AiChatController::class, 'chat'])
        ->middleware(['ai-approved', 'throttle:ai-chat'])
        ->name('ai.chat');

    Route::post('/ai/transcribe', [AiChatController::class, 'transcribe'])
        ->middleware(['ai-approved', 'throttle:ai-transcribe'])
        ->name('ai.transcribe');
});

// Account management, for the owner only.
Route::middleware(['auth', 'verified', 'admin'])->prefix('admin')->group(function () {
    Route::get('/users', [AdminUserController::class, 'index'])->name('admin.users');
    Route::patch('/users/{user}/ai', [AdminUserController::class, 'updateAiApproval'])->name('admin.users.ai');
    Route::delete('/users/{user}', [AdminUserController::class, 'destroy'])->name('admin.users.destroy');
});

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__.'/auth.php';
