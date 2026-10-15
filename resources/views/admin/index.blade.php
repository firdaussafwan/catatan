@extends('layouts.app')
@section('title', 'Admin')

@section('content')
    <header class="topbar"><h1>Admin</h1></header>

    @if ($errors->any())
        <div class="alert error">@foreach ($errors->all() as $error)<p>{{ $error }}</p>@endforeach</div>
    @endif

    <section class="panel">
        <h2><x-icon name="globe"/> Public sharing</h2>
        <p class="muted">{{ $publicPostCount }} public post{{ $publicPostCount === 1 ? '' : 's' }} · {{ $workspaceCount }} workspace{{ $workspaceCount === 1 ? '' : 's' }}.
            Reading the Public feed always works; these controls only limit who can <em>post</em>.</p>
        <form method="POST" action="{{ route('admin.settings') }}" class="form">
            @csrf @method('PUT')
            <label class="check switch">
                <input type="checkbox" name="public_enabled" value="1" @checked($publicEnabled)>
                <span><strong>Public sharing is ON</strong><br><small class="muted">Untick = kill switch: nobody can post or reply in the Public feed.</small></span>
            </label>
            <label>Posts per user per day
                <input type="number" name="daily_limit" value="{{ old('daily_limit', $dailyLimit) }}" min="0" max="1000" inputmode="numeric" required>
                <small class="muted">0 = no limit. Counts new public posts (not replies) since midnight, {{ config('app.timezone') }}.</small>
            </label>
            <button class="btn primary" type="submit">Save</button>
        </form>
    </section>

    <section class="panel">
        <h2><x-icon name="user"/> Sign-up</h2>
        <p class="muted">When open, anyone can create an account on the login page: they enter their email, get a 6-digit code (TAC) by email and confirm it.
            Close it and only the accounts you create below can get in. Existing accounts are not affected.</p>
        <form method="POST" action="{{ route('admin.registration') }}" class="form">
            @csrf @method('PUT')
            <label class="check switch">
                <input type="checkbox" name="registration_open" value="1" @checked($registrationOpen)>
                <span><strong>Sign-up is open</strong><br><small class="muted">Untick = nobody new can register.</small></span>
            </label>
            <button class="btn primary" type="submit">Save</button>
        </form>
    </section>

    <section class="panel">
        <h2><x-icon name="users"/> Accounts</h2>
        <p class="muted"><small><b>Pause</b>: read-only (can sign in and read, can not post). <b>Suspend</b>: can not sign in, for a number of days or until you reactivate. <b>Delete</b>: removes the account and everything they posted, for good.</small></p>
        <div class="users">
            @foreach ($users as $u)
                @php $me = $u->is(auth()->user()); @endphp
                <div class="user-row">
                    <div class="ws-main member">
                        <x-avatar :user="$u" size="sm"/>
                        <div>
                            <strong>{{ $u->name }}</strong>
                            @if ($u->is_admin) <span class="badge-pill">Admin</span> @endif
                            @if ($u->isSuspendedNow()) <span class="badge-pill bad">Suspended {{ $u->suspended_until ? 'until '.$u->suspended_until->format('j M, H:i') : '' }}</span> @endif
                            @if ($u->is_paused) <span class="badge-pill warn">Paused</span> @endif
                            @if ($u->public_blocked) <span class="badge-pill warn">No public posting</span> @endif
                            <br><small class="muted">{{ $u->email }} · joined {{ $u->created_at->format('j M Y') }} · {{ $todayCounts[$u->id] ?? 0 }} public post{{ ($todayCounts[$u->id] ?? 0) === 1 ? '' : 's' }} today</small>
                        </div>
                    </div>
                    <div class="ws-actions">
                        @unless ($me)
                            <form method="POST" action="{{ route('admin.users.pause', $u) }}">@csrf
                                <button class="btn sm">{{ $u->is_paused ? 'Resume' : 'Pause' }}</button>
                            </form>
                            @if ($u->isSuspendedNow())
                                <form method="POST" action="{{ route('admin.users.suspend', $u) }}">@csrf
                                    <button class="btn sm">Reactivate</button>
                                </form>
                            @else
                                <form method="POST" action="{{ route('admin.users.suspend', $u) }}" class="suspend-form" data-confirm="Suspend {{ $u->name }}? They are signed out right away.">@csrf
                                    <select name="days" aria-label="Suspend for">
                                        <option value="1">1 day</option>
                                        <option value="7">7 days</option>
                                        <option value="30">30 days</option>
                                        <option value="0">until I reactivate</option>
                                    </select>
                                    <button class="btn sm danger-btn">Suspend</button>
                                </form>
                            @endif
                        @endunless
                        <form method="POST" action="{{ route('admin.users.block', $u) }}">@csrf
                            <button class="btn sm">{{ $u->public_blocked ? 'Allow public posting' : 'Stop public posting' }}</button>
                        </form>
                        @unless ($me)
                            <form method="POST" action="{{ route('admin.users.admin', $u) }}" data-confirm="{{ $u->is_admin ? 'Remove admin rights from' : 'Make an admin:' }} {{ $u->name }}?">@csrf
                                <button class="btn sm">{{ $u->is_admin ? 'Remove admin' : 'Make admin' }}</button>
                            </form>
                        @endunless
                        <form method="POST" action="{{ route('admin.users.password', $u) }}" data-confirm="Set a new temporary password for {{ $u->name }}?">@csrf
                            <button class="btn sm">Reset password</button>
                        </form>
                        @unless ($me)
                            <form method="POST" action="{{ route('admin.users.destroy', $u) }}" data-confirm="DELETE {{ $u->name }} for good? Their account, posts, replies and files will all be removed. This can not be undone.">@csrf @method('DELETE')
                                <button class="btn sm danger-btn">Delete</button>
                            </form>
                        @endunless
                    </div>
                </div>
            @endforeach
        </div>
    </section>

    <section class="panel">
        <h2>Create an account</h2>
        <p class="muted">Make an account for someone directly (no email code needed). They can change the password in Settings.</p>
        <form method="POST" action="{{ route('admin.users.store') }}" class="form">
            @csrf
            <label>Name <input type="text" name="name" value="{{ old('name') }}" maxlength="80" required></label>
            <label>Email <input type="email" name="email" value="{{ old('email') }}" required inputmode="email"></label>
            <label>Temporary password <small>(min 8 characters)</small> <input type="text" name="password" required autocomplete="off"></label>
            <label class="check"><input type="checkbox" name="is_admin" value="1"> Make this person an admin</label>
            <button class="btn primary" type="submit">Create account</button>
        </form>
    </section>

    <section class="panel">
        <h2><x-icon name="users"/> Workspaces</h2>
        <p class="muted">Create and manage workspaces from the <a href="{{ route('workspaces.index') }}">Workspaces</a> page (admins see all of them).</p>
    </section>
@endsection
