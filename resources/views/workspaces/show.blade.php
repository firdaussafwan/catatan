@extends('layouts.app')
@section('title', $workspace->name)
@section('compose-url', route('workspaces.show', $workspace).'#composer')

@section('content')
    <header class="topbar">
        <a class="icon-btn" href="{{ route('workspaces.index') }}" aria-label="Back"><x-icon name="back"/></a>
        <h1>{{ $workspace->name }}</h1>
        @if ($canModerate)
            <a class="icon-btn" href="{{ route('workspaces.manage', $workspace) }}" aria-label="Manage workspace" title="Manage"><x-icon name="sliders"/></a>
        @endif
    </header>

    <p class="feed-intro">
        <x-icon name="users"/> {{ $workspace->members_count }} member{{ $workspace->members_count === 1 ? '' : 's' }}
        @if ($workspace->description) · {{ $workspace->description }} @endif
    </p>

    @if ($errors->any())
        <div class="alert error">@foreach ($errors->all() as $error)<p>{{ $error }}</p>@endforeach</div>
    @endif

    @include('partials.composer', [
        'action' => route('workspaces.post', $workspace),
        'placeholder' => 'Share with '.$workspace->name.'…',
        'submit' => 'Post',
        'blockReason' => $blockReason,
    ])

    <section id="feed" class="feed">
        @include('notes._list')
    </section>

    @if ($workspace->owner_id !== auth()->id() && $workspace->membershipOf(auth()->user()))
        <form method="POST" action="{{ route('workspaces.leave', $workspace) }}" class="leave-form" data-confirm="Leave {{ $workspace->name }}?">
            @csrf
            <button class="btn sm" type="submit">Leave workspace</button>
        </form>
    @endif
@endsection
