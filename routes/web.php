<?php

use App\Http\Controllers\AdminUserController;
use App\Http\Controllers\AiChatController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\PersonController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\TeamController;
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

    // Team Builder: questions, then a deck to work through, then the team itself.
    // The two fixed paths come before the wildcard so /teams/build is not read as
    // a team id.
    Route::get('/teams', [TeamController::class, 'index'])->name('teams.index');
    Route::get('/teams/build', [TeamController::class, 'build'])->name('teams.build');
    Route::get('/teams/deck', [TeamController::class, 'deck'])->name('teams.deck');
    Route::post('/teams', [TeamController::class, 'store'])->name('teams.store');
    Route::get('/teams/{team}', [TeamController::class, 'show'])->name('teams.show');
    Route::patch('/teams/{team}/people/{person}', [TeamController::class, 'respond'])->name('teams.respond');
    Route::delete('/teams/{team}', [TeamController::class, 'destroy'])->name('teams.destroy');

    // Save the filter state the list should open in, from the filter control
    // itself rather than from the category settings screen.
    Route::post('/categories/{category}/default-filter', [CategoryController::class, 'storeDefaultFilter'])
        ->name('categories.default-filter');

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
