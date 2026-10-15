<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password as PasswordRule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class AuthController extends Controller
{
    /* ------------------------------------------------------------------ login */

    public function showLogin(): View
    {
        return view('auth.login', ['canSetup' => $this->setupOpen(), 'canRegister' => \App\Support\Registration::isOpen()]);
    }

    public function login(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        // "Remember me" keeps you signed in even after the browser is closed.
        // Self-registered emails are stored in lower case, so also try that spelling.
        $remember = $request->boolean('remember');
        $ok = Auth::attempt($credentials, $remember)
            || Auth::attempt(['email' => Str::lower($credentials['email']), 'password' => $credentials['password']], $remember);

        if (! $ok) {
            throw ValidationException::withMessages(['email' => 'Email or password is incorrect.']);
        }

        // Right password, but the account is suspended (temporarily or until the admin lifts it).
        if (Auth::user()->isSuspendedNow()) {
            $message = Auth::user()->suspensionMessage();
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            throw ValidationException::withMessages(['email' => $message]);
        }

        $request->session()->regenerate();

        return redirect()->intended(route('notes.index'));
    }

    public function logout(Request $request): RedirectResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }

    /* ------------------------------------------------------- first-run setup */

    private function setupOpen(): bool
    {
        return config('catatan.allow_setup') && ! User::query()->exists();
    }

    public function showSetup(): View
    {
        abort_unless($this->setupOpen(), 404);

        return view('auth.setup');
    }

    public function setup(Request $request): RedirectResponse
    {
        abort_unless($this->setupOpen(), 404);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:80'],
            'email' => ['required', 'email', 'max:190', 'unique:users,email'],
            'password' => ['required', 'confirmed', PasswordRule::defaults()],
        ]);

        $user = User::create($data); // password is hashed by the model cast
        $user->forceFill(['is_admin' => true])->save(); // the first account is the admin

        Auth::login($user, remember: true);
        $request->session()->regenerate();

        return redirect()->route('notes.index');
    }

    /* --------------------------------------------------------- password reset */

    public function showForgot(): View
    {
        return view('auth.forgot');
    }

    public function sendResetLink(Request $request): RedirectResponse
    {
        $request->validate(['email' => ['required', 'email']]);

        Password::sendResetLink($request->only('email'));

        // Same answer whether or not the email exists (no account enumeration).
        return back()->with('status', 'If that email is registered, a reset link is on its way. Check your inbox.');
    }

    public function showReset(Request $request, string $token): View
    {
        return view('auth.reset', ['token' => $token, 'email' => $request->query('email')]);
    }

    public function reset(Request $request): RedirectResponse
    {
        $request->validate([
            'token' => ['required'],
            'email' => ['required', 'email'],
            'password' => ['required', 'confirmed', PasswordRule::defaults()],
        ]);

        $status = Password::reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function (User $user, string $password) {
                $user->forceFill([
                    'password' => $password, // hashed by the model cast
                    'remember_token' => Str::random(60), // signs out old "remember me" cookies
                ])->save();

                event(new PasswordReset($user));
            }
        );

        if ($status === Password::PASSWORD_RESET) {
            return redirect()->route('login')->with('status', 'Password updated. Please sign in.');
        }

        return back()->withInput($request->only('email'))
            ->withErrors(['email' => 'This reset link is invalid or has expired. Request a new one.']);
    }

    /* ---------------------------------------------------------------- settings */

    public function showSettings(): View
    {
        return view('auth.settings');
    }

    public function updatePassword(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'current_password' => ['required', 'current_password'],
            'password' => ['required', 'confirmed', PasswordRule::defaults()],
        ]);

        $request->user()->forceFill(['password' => $data['password']])->save();

        return back()->with('status', 'Password changed.');
    }

    /* ----------------------------------------------------------- profile picture */

    public function updateAvatar(Request $request): RedirectResponse
    {
        $request->validate(['avatar' => ['required', 'image', 'max:5120']]);

        $source = @imagecreatefromstring((string) file_get_contents($request->file('avatar')->getRealPath()));
        if (! $source) {
            throw ValidationException::withMessages(['avatar' => 'Could not read that image.']);
        }

        // Centre-crop to a square and shrink to 256px: small file, sharp on every screen.
        $w = imagesx($source);
        $h = imagesy($source);
        $side = min($w, $h);
        $square = imagecreatetruecolor(256, 256);
        imagefill($square, 0, 0, imagecolorallocate($square, 255, 255, 255));
        imagecopyresampled($square, $source, 0, 0, intdiv($w - $side, 2), intdiv($h - $side, 2), 256, 256, $side, $side);

        ob_start();
        imagejpeg($square, null, 85);
        $jpeg = ob_get_clean();

        $user = $request->user();
        $path = 'avatars/'.$user->id.'/'.Str::random(40).'.jpg';
        Storage::disk('local')->put($path, $jpeg);

        if ($user->avatar_path) {
            Storage::disk('local')->delete($user->avatar_path);
        }
        $user->forceFill(['avatar_path' => $path])->save();

        return back()->with('status', 'Profile picture updated.');
    }

    public function removeAvatar(Request $request): RedirectResponse
    {
        $user = $request->user();

        if ($user->avatar_path) {
            Storage::disk('local')->delete($user->avatar_path);
            $user->forceFill(['avatar_path' => null])->save();
        }

        return back()->with('status', 'Profile picture removed.');
    }
}
