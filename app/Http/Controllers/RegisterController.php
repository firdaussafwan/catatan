<?php

namespace App\Http\Controllers;

use App\Models\PendingRegistration;
use App\Models\User;
use App\Support\Registration;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password as PasswordRule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Self sign-up: details -> 6-digit code by email (TAC) -> account.
 * No user row exists until the code is confirmed, so unconfirmed sign-ups leave nothing behind.
 */
class RegisterController extends Controller
{
    public function show(): View
    {
        $this->ensureOpen();

        return view('auth.register');
    }

    public function store(Request $request): RedirectResponse
    {
        $this->ensureOpen();

        $data = $request->validate([
            'name' => ['required', 'string', 'max:80'],
            'email' => ['required', 'email', 'max:190'],
            'password' => ['required', 'confirmed', PasswordRule::defaults()],
        ]);
        $email = Str::lower($data['email']);

        if (User::whereRaw('lower(email) = ?', [$email])->exists()) {
            throw ValidationException::withMessages(['email' => 'This email already has an account. Sign in, or use "Forgot password".']);
        }

        PendingRegistration::where('expires_at', '<', now()->subDay())->delete(); // housekeeping
        Registration::start($data['name'], $email, $data['password'], $request->ip());

        return redirect()->route('register.verify', ['email' => $email])
            ->with('status', "We emailed a 6-digit code to {$email}. It works for ".Registration::TTL_MINUTES.' minutes.');
    }

    public function showVerify(Request $request): View
    {
        $this->ensureOpen();

        return view('auth.verify', ['email' => old('email', $request->query('email'))]);
    }

    public function verify(Request $request): RedirectResponse
    {
        $this->ensureOpen();

        $data = $request->validate([
            'email' => ['required', 'email'],
            'code' => ['required', 'digits:6'],
        ]);
        $email = Str::lower($data['email']);
        $fail = fn (string $message) => back()->withInput(['email' => $email])->withErrors(['code' => $message]);

        $pending = PendingRegistration::where('email', $email)->first();

        if (! $pending) {
            return $fail('There is no pending sign-up for this email. Please register again.');
        }
        if ($pending->attempts >= Registration::MAX_ATTEMPTS) {
            $pending->delete();

            return $fail('Too many wrong codes. Please register again.');
        }
        if ($pending->isExpired()) {
            return $fail('This code has expired. Tap "Send me a new code".');
        }
        if (! $pending->codeMatches($data['code'])) {
            $pending->increment('attempts');
            $left = Registration::MAX_ATTEMPTS - $pending->attempts;

            if ($left <= 0) {
                $pending->delete();

                return $fail('Too many wrong codes. Please register again.');
            }

            return $fail("That code is not right. {$left} ".($left === 1 ? 'try' : 'tries').' left.');
        }

        if (User::whereRaw('lower(email) = ?', [$email])->exists()) { // someone else finished first
            $pending->delete();

            return redirect()->route('login')->with('status', 'This email already has an account. Please sign in.');
        }

        $user = User::create([
            'name' => $pending->name,
            'email' => $pending->email,
            'password' => Crypt::decryptString($pending->password), // hashed by the model cast
        ]);
        $user->forceFill(['email_verified_at' => now()])->save();
        $pending->delete();

        Auth::login($user, remember: true);
        $request->session()->regenerate();

        return redirect()->route('notes.index')->with('status', 'Welcome! Your email is confirmed.');
    }

    public function resend(Request $request): RedirectResponse
    {
        $this->ensureOpen();

        $email = Str::lower($request->validate(['email' => ['required', 'email']])['email']);
        $pending = PendingRegistration::where('email', $email)->first();

        if ($pending) {
            $wait = Registration::RESEND_SECONDS - (now()->timestamp - $pending->last_sent_at->timestamp);
            if ($wait > 0) {
                return back()->withInput(['email' => $email])
                    ->withErrors(['code' => "Please wait {$wait} seconds before asking for another code."]);
            }

            Registration::resend($pending);
        }

        // Same answer whether or not a sign-up is waiting for this email.
        return back()->withInput(['email' => $email])
            ->with('status', 'If a sign-up is waiting for that email, a new code is on its way.');
    }

    private function ensureOpen(): void
    {
        abort_unless(Registration::isOpen(), 404);
    }
}
