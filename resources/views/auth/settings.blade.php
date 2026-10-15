@extends('layouts.app')
@section('title', 'Settings')

@section('content')
    <header class="topbar"><h1>Settings</h1></header>

    <section class="panel">
        <h2>Profile picture</h2>
        <div class="profile-row">
            <x-avatar size="lg"/>
            <div class="profile-actions">
                <p class="muted" style="margin:0">{{ auth()->user()->name }} · {{ auth()->user()->email }}</p>
                <form method="POST" action="{{ route('settings.avatar') }}" enctype="multipart/form-data" class="form" id="avatar-form">
                    @csrf
                    <input type="file" name="avatar" accept="image/*" required aria-label="Choose picture">
                    <button class="btn primary" type="submit">Upload picture</button>
                </form>
                @if (auth()->user()->avatar_path)
                    <form method="POST" action="{{ route('settings.avatar.remove') }}">
                        @csrf
                        @method('DELETE')
                        <button class="btn" type="submit">Remove picture</button>
                    </form>
                @endif
                <small>JPG, PNG, WebP or GIF up to 5 MB. It is cropped to a square automatically.</small>
            </div>
        </div>
    </section>

    <section class="panel shortcuts">
        <h2>Shortcuts</h2>
        <div class="chips">
            <a class="chip" href="{{ route('notes.index', ['starred' => 1]) }}"><x-icon name="star"/> Starred</a>
            <a class="chip" href="{{ route('notes.index', ['due' => 1]) }}"><x-icon name="clock"/> Due dates</a>
            @if (auth()->user()->is_admin)
                <a class="chip" href="{{ route('admin.index') }}"><x-icon name="shield"/> Admin</a>
            @endif
        </div>
    </section>

    <section class="panel">
        <h2>Change password</h2>
        @if ($errors->any())
            <div class="alert error">@foreach ($errors->all() as $error)<p>{{ $error }}</p>@endforeach</div>
        @endif
        <form method="POST" action="{{ route('settings.password') }}" class="form">
            @csrf
            @method('PUT')
            <label>Current password
                <input type="password" name="current_password" required autocomplete="current-password">
            </label>
            <label>New password <small>(min 8 characters)</small>
                <input type="password" name="password" required autocomplete="new-password">
            </label>
            <label>Confirm new password
                <input type="password" name="password_confirmation" required autocomplete="new-password">
            </label>
            <button class="btn primary" type="submit">Update password</button>
        </form>
    </section>

    <section class="panel">
        <h2>Appearance & session</h2>
        <button type="button" class="btn" data-theme-toggle><x-icon name="moon"/> Switch dark / light</button>
        <form method="POST" action="{{ route('logout') }}" style="margin-top:.75rem">
            @csrf
            <button class="btn" type="submit"><x-icon name="logout"/> Sign out</button>
        </form>
    </section>
@endsection
