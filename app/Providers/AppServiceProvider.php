<?php

namespace App\Providers;

use App\Models\Note;
use App\Models\Attachment;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        Password::defaults(fn () => Password::min(8));

        // Brute-force protection for the login form: 5 tries / minute per email + IP.
        RateLimiter::for('login', function (Request $request) {
            return Limit::perMinute(5)->by(Str::lower((string) $request->input('email')).'|'.$request->ip());
        });

        // Sign-up form: 10 attempts per hour per IP (each one sends an email).
        RateLimiter::for('register', fn (Request $request) => Limit::perHour(10)->by($request->ip()));

        // SECURITY: route-model bindings are scoped to what the signed-in user may see.
        // Anything else is a plain 404, never "forbidden", so nothing leaks.

        // {note}: one of MY notes (edit / update / delete / star / pin)
        Route::bind('note', fn ($value) => Note::where('user_id', auth()->id())->findOrFail($value));

        // {thread}: any note I may read (my private ones, public posts, posts of my workspaces)
        Route::bind('thread', fn ($value) => Note::visibleTo(auth()->user())->findOrFail($value));

        // {attachment}: a file attached to a note I may read
        Route::bind('attachment', fn ($value) => Attachment::whereHas(
            'note', fn ($q) => $q->visibleTo(auth()->user())
        )->findOrFail($value));
    }
}
