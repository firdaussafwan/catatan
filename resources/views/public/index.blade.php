@extends('layouts.app')
@section('title', 'Public')
@section('compose-url', route('public.index').'#composer')

@section('content')
    <header class="topbar">
        <h1>Public</h1>
        <form method="GET" action="{{ route('public.index') }}" class="topbar-search" role="search">
            <input type="search" name="q" value="{{ $q }}" placeholder="Search public posts…" maxlength="100" aria-label="Search public posts">
        </form>
    </header>

    <p class="feed-intro"><x-icon name="globe"/> Everyone with an account can see this feed.
        @if (! $blockReason && $postsLeft !== null) <strong>{{ $postsLeft }}</strong> post{{ $postsLeft === 1 ? '' : 's' }} left today. @endif
    </p>

    @if ($errors->any())
        <div class="alert error">@foreach ($errors->all() as $error)<p>{{ $error }}</p>@endforeach</div>
    @endif

    @include('partials.composer', [
        'action' => route('public.store'),
        'placeholder' => 'Share something with everyone…',
        'submit' => 'Share',
        'blockReason' => $blockReason,
    ])

    <section id="feed" class="feed">
        @include('notes._list')
    </section>
@endsection
