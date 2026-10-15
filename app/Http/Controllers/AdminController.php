<?php

namespace App\Http\Controllers;

use App\Models\Note;
use App\Models\Setting;
use App\Models\User;
use App\Models\Workspace;
use App\Support\Registration;
use App\Support\Sharing;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/** Admin panel: the public-sharing kill switch + daily limit, and account management. */
class AdminController extends Controller
{
    public function index(): View
    {
        $today = Note::where('visibility', 'public')->whereNull('parent_id')
            ->where('created_at', '>=', now()->startOfDay())
            ->selectRaw('user_id, count(*) as c')->groupBy('user_id')->pluck('c', 'user_id');

        return view('admin.index', [
            'registrationOpen' => Setting::bool(Registration::KEY, true),
            'publicEnabled' => Sharing::publicEnabled(),
            'dailyLimit' => Sharing::dailyLimit(),
            'users' => User::orderByDesc('is_admin')->orderBy('name')->get(),
            'todayCounts' => $today,
            'workspaceCount' => Workspace::count(),
            'publicPostCount' => Note::where('visibility', 'public')->whereNull('parent_id')->count(),
        ]);
    }

    public function updateSettings(Request $request): RedirectResponse
    {
        $data = $request->validate(['daily_limit' => ['required', 'integer', 'between:0,1000']]);

        Setting::put(Sharing::KILL_SWITCH, $request->boolean('public_enabled'));
        Setting::put(Sharing::DAILY_LIMIT, $data['daily_limit']);

        return back()->with('status', 'Settings saved.');
    }

    public function storeUser(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:80'],
            'email' => ['required', 'email', 'max:190', 'unique:users,email'],
            'password' => ['required', Password::defaults()],
        ]);

        $user = User::create($data);
        $user->forceFill(['is_admin' => $request->boolean('is_admin')])->save();

        return back()->with('status', "Account created for {$user->email}. Give them the password; they can change it in Settings.");
    }

    /** Stop (or allow again) this user from posting to the Public feed. */
    public function togglePublicBlock(User $user): RedirectResponse
    {
        $user->forceFill(['public_blocked' => ! $user->public_blocked])->save();

        return back()->with('status', $user->public_blocked ? "{$user->name} can no longer post publicly." : "{$user->name} can post publicly again.");
    }

    /** Open / close self sign-up (people can still be given accounts from this page). */
    public function updateRegistration(Request $request): RedirectResponse
    {
        Setting::put(Registration::KEY, $request->boolean('registration_open'));

        return back()->with('status', $request->boolean('registration_open') ? 'Sign-up is open.' : 'Sign-up is closed.');
    }

    /** Pause = read-only: the person can sign in and read, but can not post, reply, edit or upload. */
    public function togglePause(Request $request, User $user): RedirectResponse
    {
        $this->notSelf($request, $user, 'You can not pause your own account.');

        $user->forceFill(['is_paused' => ! $user->is_paused])->save();

        return back()->with('status', $user->is_paused ? "{$user->name} is paused (read-only)." : "{$user->name} can post again.");
    }

    /**
     * Temporary terminate: the person can not sign in. "days" = for how long (0 or empty = until you reactivate).
     * Calling it again on a suspended account reactivates it.
     */
    public function toggleSuspend(Request $request, User $user): RedirectResponse
    {
        $this->notSelf($request, $user, 'You can not suspend your own account.');

        if ($user->isSuspendedNow()) {
            $user->forceFill(['is_suspended' => false, 'suspended_until' => null])->save();

            return back()->with('status', "{$user->name} is active again.");
        }

        $days = (int) ($request->validate(['days' => ['nullable', 'integer', 'between:0,365']])['days'] ?? 0);
        $until = $days > 0 ? now()->addDays($days) : null;

        $user->forceFill(['is_suspended' => true, 'suspended_until' => $until, 'remember_token' => Str::random(60)])->save();
        DB::table('sessions')->where('user_id', $user->id)->delete(); // signed out immediately, on every device

        return back()->with('status', $until
            ? "{$user->name} is suspended until {$until->format('j M Y, H:i')}."
            : "{$user->name} is suspended until you reactivate the account.");
    }

    /** Permanently delete an account and everything it posted. Refused while the person still owns a workspace. */
    public function destroy(Request $request, User $user): RedirectResponse
    {
        $this->notSelf($request, $user, 'You can not delete your own account.');

        $owned = Workspace::where('owner_id', $user->id)->pluck('name');
        if ($owned->isNotEmpty()) {
            throw ValidationException::withMessages(['user' => "{$user->name} still owns the workspace(s): {$owned->join(', ')}. Delete those workspaces first (Workspaces page)."]);
        }

        $name = $user->name;
        $user->deleteWithData();

        return back()->with('status', "{$name}'s account and everything they posted were deleted.");
    }

    public function toggleAdmin(Request $request, User $user): RedirectResponse
    {
        $this->notSelf($request, $user, 'You can not change your own admin role.');

        $user->forceFill(['is_admin' => ! $user->is_admin])->save();

        return back()->with('status', $user->is_admin ? "{$user->name} is now an admin." : "{$user->name} is no longer an admin.");
    }

    /** Set a temporary password (shown once) for someone who is locked out. */
    public function resetPassword(User $user): RedirectResponse
    {
        $temporary = Str::password(12, symbols: false);
        $user->forceFill(['password' => $temporary, 'remember_token' => Str::random(60)])->save();

        return back()->with('status', "Temporary password for {$user->email}: {$temporary}  (shown once; ask them to change it)");
    }

    private function notSelf(Request $request, User $user, string $message): void
    {
        if ($request->user()->is($user)) {
            throw ValidationException::withMessages(['user' => $message]);
        }
    }
}
