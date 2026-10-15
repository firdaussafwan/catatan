<?php

use App\Http\Controllers\AdminController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\MediaController;
use App\Http\Controllers\NoteController;
use App\Http\Controllers\PublicController;
use App\Http\Controllers\RegisterController;
use App\Http\Controllers\WorkspaceController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Guest: front page is the login screen
|--------------------------------------------------------------------------
*/
Route::middleware('guest')->group(function () {
    Route::get('/', [AuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:login')->name('login.attempt');

    // First-run account creation (closed automatically once a user exists)
    Route::get('/setup', [AuthController::class, 'showSetup'])->name('setup');
    Route::post('/setup', [AuthController::class, 'setup'])->name('setup.store');

    // Open sign-up with an emailed code (TAC); the admin can switch it off
    Route::get('/register', [RegisterController::class, 'show'])->name('register');
    Route::post('/register', [RegisterController::class, 'store'])->middleware('throttle:register')->name('register.store');
    Route::get('/register/verify', [RegisterController::class, 'showVerify'])->name('register.verify');
    Route::post('/register/verify', [RegisterController::class, 'verify'])->middleware('throttle:10,1')->name('register.verify.store');
    Route::post('/register/resend', [RegisterController::class, 'resend'])->middleware('throttle:5,1')->name('register.resend');

    // Password reset by email
    Route::get('/forgot-password', [AuthController::class, 'showForgot'])->name('password.request');
    Route::post('/forgot-password', [AuthController::class, 'sendResetLink'])->middleware('throttle:5,1')->name('password.email');
    Route::get('/reset-password/{token}', [AuthController::class, 'showReset'])->name('password.reset');
    Route::post('/reset-password', [AuthController::class, 'reset'])->middleware('throttle:5,1')->name('password.update');
});

/*
|--------------------------------------------------------------------------
| Signed-in area
|--------------------------------------------------------------------------
*/
Route::middleware('auth')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
    Route::get('/settings', [AuthController::class, 'showSettings'])->name('settings');
    Route::post('/settings/avatar', [AuthController::class, 'updateAvatar'])->name('settings.avatar');
    Route::delete('/settings/avatar', [AuthController::class, 'removeAvatar'])->name('settings.avatar.remove');
    Route::put('/settings/password', [AuthController::class, 'updatePassword'])->name('settings.password');

    // --- My notes (private) --------------------------------------------------------------
    Route::get('/home', [NoteController::class, 'index'])->name('notes.index');
    Route::post('/notes', [NoteController::class, 'store'])->middleware('writer')->name('notes.store');
    Route::get('/notes/{note}/edit', [NoteController::class, 'edit'])->name('notes.edit');
    Route::put('/notes/{note}', [NoteController::class, 'update'])->middleware('writer')->name('notes.update');
    Route::delete('/notes/{note}', [NoteController::class, 'destroy'])->name('notes.destroy');
    Route::post('/notes/{note}/star', [NoteController::class, 'toggleStar'])->name('notes.star');
    Route::post('/notes/{note}/pin', [NoteController::class, 'togglePin'])->name('notes.pin');

    // --- Any thread I may read (private, public or workspace) -----------------------------
    Route::get('/notes/{thread}', [NoteController::class, 'show'])->name('notes.show');
    Route::post('/notes/{thread}/replies', [NoteController::class, 'reply'])->middleware('writer')->name('notes.reply');
    Route::delete('/notes/{thread}/moderate', [NoteController::class, 'moderate'])->name('notes.moderate');

    // --- Public feed ------------------------------------------------------------------------
    Route::get('/public', [PublicController::class, 'index'])->name('public.index');
    Route::post('/public', [PublicController::class, 'store'])->middleware('writer')->name('public.store');

    // --- Workspaces --------------------------------------------------------------------------
    Route::get('/workspaces', [WorkspaceController::class, 'index'])->name('workspaces.index');
    Route::post('/workspaces', [WorkspaceController::class, 'store'])->name('workspaces.store');          // admin
    Route::get('/join/{token}', [WorkspaceController::class, 'joinShow'])->name('workspaces.join');
    Route::post('/join/{token}', [WorkspaceController::class, 'joinStore'])->name('workspaces.join.store');

    Route::get('/w/{workspace}', [WorkspaceController::class, 'show'])->name('workspaces.show');
    Route::post('/w/{workspace}/posts', [WorkspaceController::class, 'post'])->middleware('writer')->name('workspaces.post');
    Route::post('/w/{workspace}/leave', [WorkspaceController::class, 'leave'])->name('workspaces.leave');
    Route::post('/w/{workspace}/request', [WorkspaceController::class, 'requestJoin'])->name('workspaces.request');
    Route::delete('/w/{workspace}/request', [WorkspaceController::class, 'cancelRequest'])->name('workspaces.request.cancel');

    // owner / admin only
    Route::get('/w/{workspace}/manage', [WorkspaceController::class, 'manage'])->name('workspaces.manage');
    Route::put('/w/{workspace}', [WorkspaceController::class, 'update'])->name('workspaces.update');
    Route::delete('/w/{workspace}/messages', [WorkspaceController::class, 'clear'])->name('workspaces.clear');
    Route::delete('/w/{workspace}', [WorkspaceController::class, 'destroy'])->name('workspaces.destroy');
    Route::post('/w/{workspace}/link', [WorkspaceController::class, 'regenerateLink'])->name('workspaces.link');
    Route::post('/w/{workspace}/members/{member}/restrict', [WorkspaceController::class, 'toggleRestrict'])->name('workspaces.members.restrict');
    Route::delete('/w/{workspace}/members/{member}', [WorkspaceController::class, 'kick'])->name('workspaces.members.kick');
    Route::post('/w/{workspace}/requests/{joinRequest}', [WorkspaceController::class, 'approve'])->name('workspaces.requests.approve');
    Route::delete('/w/{workspace}/requests/{joinRequest}', [WorkspaceController::class, 'deny'])->name('workspaces.requests.deny');

    // --- Files (private: served only to people who can read the note) ---------------------------
    Route::get('/files/{attachment}', [MediaController::class, 'show'])->name('attachments.show');
    Route::get('/files/{attachment}/download', [MediaController::class, 'download'])->name('attachments.download');
    Route::get('/avatar/{user}', [MediaController::class, 'avatar'])->name('avatar.show');

    // --- Admin ----------------------------------------------------------------------------------
    Route::middleware('admin')->prefix('admin')->group(function () {
        Route::get('/', [AdminController::class, 'index'])->name('admin.index');
        Route::put('/settings', [AdminController::class, 'updateSettings'])->name('admin.settings');
        Route::post('/users', [AdminController::class, 'storeUser'])->name('admin.users.store');
        Route::post('/users/{user}/public-block', [AdminController::class, 'togglePublicBlock'])->name('admin.users.block');
        Route::put('/registration', [AdminController::class, 'updateRegistration'])->name('admin.registration');
        Route::post('/users/{user}/pause', [AdminController::class, 'togglePause'])->name('admin.users.pause');
        Route::delete('/users/{user}', [AdminController::class, 'destroy'])->name('admin.users.destroy');
        Route::post('/users/{user}/suspend', [AdminController::class, 'toggleSuspend'])->name('admin.users.suspend');
        Route::post('/users/{user}/admin', [AdminController::class, 'toggleAdmin'])->name('admin.users.admin');
        Route::post('/users/{user}/password', [AdminController::class, 'resetPassword'])->name('admin.users.password');
    });
});
