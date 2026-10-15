@extends('layouts.app')
@section('title', 'Join '.$workspace->name)

@section('content')
    <header class="topbar"><h1>Join a workspace</h1></header>

    <section class="panel join-card">
        <x-icon name="users"/>
        <h2>{{ $workspace->name }}</h2>
        @if ($workspace->description) <p>{{ $workspace->description }}</p> @endif
        <p class="muted">{{ $workspace->members_count }} member{{ $workspace->members_count === 1 ? '' : 's' }} · owner {{ $workspace->owner->name }}</p>
        <form method="POST" action="{{ route('workspaces.join.store', $workspace->join_token) }}">
            @csrf
            <button class="btn primary wide" type="submit">Join workspace</button>
        </form>
        <p class="muted"><small>Members can read everything posted here. The owner can remove members and delete messages.</small></p>
    </section>
@endsection
