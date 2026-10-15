@extends('layouts.app')
@section('title', 'Workspaces')

@section('content')
    <header class="topbar"><h1>Workspaces</h1></header>

    @if ($errors->any())
        <div class="alert error">@foreach ($errors->all() as $error)<p>{{ $error }}</p>@endforeach</div>
    @endif

    <section class="panel">
        <h2>{{ auth()->user()->is_admin ? 'All workspaces' : 'Your workspaces' }}</h2>
        @forelse ($mine as $w)
            <div class="ws-row">
                <div class="ws-main">
                    <a class="ws-name" href="{{ route('workspaces.show', $w) }}">{{ $w->name }}</a>
                    @if ($w->description) <p class="muted ws-desc">{{ $w->description }}</p> @endif
                    <small class="muted">{{ $w->members_count }} member{{ $w->members_count === 1 ? '' : 's' }} · owner {{ $w->owner->name }}</small>
                </div>
                <div class="ws-actions">
                    <a class="btn sm" href="{{ route('workspaces.show', $w) }}">Open</a>
                    @if ($w->canBeManagedBy(auth()->user()))
                        <a class="btn sm" href="{{ route('workspaces.manage', $w) }}">Manage
                            @if (($pending[$w->id] ?? 0) > 0)<span class="dot-badge">{{ $pending[$w->id] }}</span>@endif
                        </a>
                    @endif
                </div>
            </div>
        @empty
            <p class="muted">You are not in any workspace yet. Open an invite link someone shared with you, or ask to join one below.</p>
        @endforelse
    </section>

    @if ($discover->isNotEmpty())
        <section class="panel">
            <h2>Find a workspace</h2>
            @foreach ($discover as $w)
                <div class="ws-row">
                    <div class="ws-main">
                        <strong class="ws-name">{{ $w->name }}</strong>
                        @if ($w->description) <p class="muted ws-desc">{{ $w->description }}</p> @endif
                        <small class="muted">{{ $w->members_count }} member{{ $w->members_count === 1 ? '' : 's' }} · owner {{ $w->owner->name }}</small>
                    </div>
                    <div class="ws-actions">
                        @if (in_array($w->id, $requested, true))
                            <form method="POST" action="{{ route('workspaces.request.cancel', $w) }}">@csrf @method('DELETE')
                                <button class="btn sm">Requested · cancel</button>
                            </form>
                        @else
                            <form method="POST" action="{{ route('workspaces.request', $w) }}">@csrf
                                <button class="btn sm primary">Request to join</button>
                            </form>
                        @endif
                    </div>
                </div>
            @endforeach
        </section>
    @endif

    @if (auth()->user()->is_admin)
        <section class="panel">
            <h2>Open a new workspace</h2>
            <form method="POST" action="{{ route('workspaces.store') }}" class="form">
                @csrf
                <label>Name
                    <input type="text" name="name" value="{{ old('name') }}" maxlength="80" required>
                </label>
                <label>Description <small>(optional)</small>
                    <input type="text" name="description" value="{{ old('description') }}" maxlength="300">
                </label>
                <label>Owner
                    <select name="owner_id">
                        @foreach ($candidates as $c)
                            <option value="{{ $c->id }}" @selected((int) old('owner_id', auth()->id()) === $c->id)>{{ $c->name }} ({{ $c->email }})</option>
                        @endforeach
                    </select>
                </label>
                <button class="btn primary" type="submit">Create workspace</button>
            </form>
        </section>
    @endif
@endsection
