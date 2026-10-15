@extends('layouts.base')

@section('body-class', 'app')

@section('body')
<div class="shell">
    {{-- Desktop sidebar --}}
    <aside class="nav">
        <a class="brand" href="{{ route('notes.index') }}">
            <img src="{{ asset('icon.svg') }}" alt="" width="32" height="32">
            <span>{{ config('app.name') }}</span>
        </a>
        <nav class="nav-links">
            <a href="{{ route('notes.index') }}" @class(['active' => request()->routeIs('notes.index') && ! request('starred') && ! request('due')])><x-icon name="home"/> <span>My notes</span></a>
            <a href="{{ route('public.index') }}" @class(['active' => request()->routeIs('public.*')])><x-icon name="globe"/> <span>Public</span></a>
            <a href="{{ route('workspaces.index') }}" @class(['active' => request()->routeIs('workspaces.*')])><x-icon name="users"/> <span>Workspaces</span></a>
            <a href="{{ route('notes.index', ['starred' => 1]) }}" @class(['active' => request()->routeIs('notes.index') && request('starred')])><x-icon name="star"/> <span>Starred</span></a>
            <a href="{{ route('notes.index', ['due' => 1]) }}" @class(['active' => request()->routeIs('notes.index') && request('due')])><x-icon name="clock"/> <span>Due dates</span></a>
            @if (auth()->user()->is_admin)
                <a href="{{ route('admin.index') }}" @class(['active' => request()->routeIs('admin.*')])><x-icon name="shield"/> <span>Admin</span></a>
            @endif
            <a href="{{ route('settings') }}" @class(['active' => request()->routeIs('settings')])><x-icon name="user"/> <span>Settings</span></a>
            <button type="button" data-theme-toggle><x-icon name="moon"/> <span>Dark / light</span></button>
        </nav>
        @php $compose = trim($__env->yieldContent('compose-url')) ?: route('notes.index').'#composer'; @endphp
        <a class="btn primary wide" href="{{ $compose }}">New post</a>
        <form method="POST" action="{{ route('logout') }}" class="nav-foot">
            @csrf
            <div class="me">
                <x-avatar size="sm"/>
                <div class="me-text"><strong>{{ auth()->user()->name }}</strong><small>{{ auth()->user()->email }}</small></div>
            </div>
            <button class="icon-btn" title="Sign out" aria-label="Sign out"><x-icon name="logout"/></button>
        </form>
    </aside>

    <main class="main">
        @if (session('status'))
            <div class="alert ok" role="status">{{ session('status') }}</div>
        @endif
        @yield('content')
    </main>

    <aside class="rail" id="rail" aria-label="Search and filters">
        @isset($filters)
            @include('partials.rail')
        @endisset
    </aside>
</div>
<div class="sheet-backdrop" data-sheet-toggle></div>

{{-- Mobile bottom bar --}}
<nav class="tabbar" aria-label="Main">
    <a href="{{ route('notes.index') }}" @class(['active' => request()->routeIs('notes.index', 'notes.edit')]) aria-label="My notes"><x-icon name="home"/></a>
    <a href="{{ route('public.index') }}" @class(['active' => request()->routeIs('public.*')]) aria-label="Public"><x-icon name="globe"/></a>
    <a href="{{ $compose }}" class="fab" aria-label="New post"><x-icon name="plus"/></a>
    <a href="{{ route('workspaces.index') }}" @class(['active' => request()->routeIs('workspaces.*')]) aria-label="Workspaces"><x-icon name="users"/></a>
    <a href="{{ route('settings') }}" @class(['active' => request()->routeIs('settings', 'admin.*')]) aria-label="Menu"><x-icon name="user"/></a>
</nav>
@endsection
