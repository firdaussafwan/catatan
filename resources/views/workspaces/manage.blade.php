@extends('layouts.app')
@section('title', 'Manage '.$workspace->name)

@section('content')
    <header class="topbar">
        <a class="icon-btn" href="{{ route('workspaces.show', $workspace) }}" aria-label="Back"><x-icon name="back"/></a>
        <h1>Manage · {{ $workspace->name }}</h1>
    </header>

    @if ($errors->any())
        <div class="alert error">@foreach ($errors->all() as $error)<p>{{ $error }}</p>@endforeach</div>
    @endif

    {{-- Invite link --}}
    <section class="panel">
        <h2>Invite link</h2>
        @if ($workspace->invite_enabled)
            <p class="muted">Anyone with an account who opens this link can join straight away.</p>
            <div class="copy-row">
                <input type="text" readonly value="{{ $workspace->joinUrl() }}" id="join-link" aria-label="Invite link">
                <button type="button" class="btn" data-copy="#join-link"><x-icon name="copy"/> Copy</button>
            </div>
            <form method="POST" action="{{ route('workspaces.link', $workspace) }}" data-confirm="Create a new link? The old link will stop working.">
                @csrf
                <button class="btn sm" type="submit">Make a new link</button>
            </form>
        @else
            <p class="muted">The invite link is switched off. Turn it on in the settings below.</p>
        @endif
    </section>

    {{-- Join requests --}}
    <section class="panel">
        <h2>Join requests @if ($requests->isNotEmpty())<span class="dot-badge">{{ $requests->count() }}</span>@endif</h2>
        @forelse ($requests as $r)
            <div class="ws-row">
                <div class="ws-main member">
                    <x-avatar :user="$r->user" size="sm"/>
                    <div><strong>{{ $r->user->name }}</strong><br><small class="muted">{{ $r->user->email }} · {{ $r->created_at->diffForHumans() }}</small></div>
                </div>
                <div class="ws-actions">
                    <form method="POST" action="{{ route('workspaces.requests.approve', [$workspace, $r]) }}">@csrf<button class="btn sm primary">Approve</button></form>
                    <form method="POST" action="{{ route('workspaces.requests.deny', [$workspace, $r]) }}">@csrf @method('DELETE')<button class="btn sm">Decline</button></form>
                </div>
            </div>
        @empty
            <p class="muted">No pending requests.</p>
        @endforelse
    </section>

    {{-- Members --}}
    <section class="panel">
        <h2>Members ({{ $members->count() }})</h2>
        <p class="muted"><small><b>Restrict</b>: can read but not post or reply. <b>Remove</b>: kicked out (can rejoin with a valid link unless you make a new one).</small></p>
        @foreach ($members as $m)
            <div class="ws-row">
                <div class="ws-main member">
                    <x-avatar :user="$m->user" size="sm"/>
                    <div>
                        <strong>{{ $m->user->name }}</strong>
                        @if ($m->isOwner()) <span class="badge-pill">Owner</span> @endif
                        @if ($m->is_restricted) <span class="badge-pill warn">Restricted</span> @endif
                        <br><small class="muted">{{ $m->user->email }}</small>
                    </div>
                </div>
                @unless ($m->isOwner())
                    <div class="ws-actions">
                        <form method="POST" action="{{ route('workspaces.members.restrict', [$workspace, $m]) }}">@csrf
                            <button class="btn sm">{{ $m->is_restricted ? 'Allow posting' : 'Restrict' }}</button>
                        </form>
                        <form method="POST" action="{{ route('workspaces.members.kick', [$workspace, $m]) }}" class="kick-form" data-confirm="Remove {{ $m->user->name }} from {{ $workspace->name }}?">
                            @csrf @method('DELETE')
                            <label class="check"><input type="checkbox" name="delete_messages" value="1"> also delete their messages</label>
                            <button class="btn sm danger-btn">Remove</button>
                        </form>
                    </div>
                @endunless
            </div>
        @endforeach
    </section>

    {{-- Settings --}}
    <section class="panel">
        <h2>Settings</h2>
        <form method="POST" action="{{ route('workspaces.update', $workspace) }}" class="form">
            @csrf @method('PUT')
            <label>Name
                <input type="text" name="name" value="{{ old('name', $workspace->name) }}" maxlength="80" required>
            </label>
            <label>Description
                <input type="text" name="description" value="{{ old('description', $workspace->description) }}" maxlength="300">
            </label>
            <label class="check"><input type="checkbox" name="invite_enabled" value="1" @checked($workspace->invite_enabled)> Invite link works</label>
            <label class="check"><input type="checkbox" name="is_listed" value="1" @checked($workspace->is_listed)> Show in the workspace directory (people can request to join)</label>
            <button class="btn primary" type="submit">Save</button>
        </form>
    </section>

    <section class="panel danger-zone">
        <h2>Clear chat</h2>
        <p class="muted">Deletes every message and file but keeps the workspace and its members.</p>
        <form method="POST" action="{{ route('workspaces.clear', $workspace) }}" data-confirm="Delete ALL messages and files in {{ $workspace->name }}?">
            @csrf @method('DELETE')
            <button class="btn danger-btn" type="submit">Clear all messages</button>
        </form>
    </section>

    <section class="panel danger-zone">
        <h2>Delete workspace</h2>
        <p class="muted">Deletes the workspace, every message and every file in it. This can not be undone.</p>
        <form method="POST" action="{{ route('workspaces.destroy', $workspace) }}" data-confirm="Delete {{ $workspace->name }} and ALL its messages and files?">
            @csrf @method('DELETE')
            <button class="btn danger-btn" type="submit">Delete workspace</button>
        </form>
    </section>
@endsection
