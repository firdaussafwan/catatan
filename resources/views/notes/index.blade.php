@extends('layouts.app')
@section('title', 'My notes')

@section('content')
    @php
        $hasFilter = collect($filters)->filter(fn ($v) => $v !== null && $v !== '' && $v !== '0')->isNotEmpty();
    @endphp

    <header class="topbar">
        <h1>{{ $hasFilter ? 'Results' : 'My notes' }}</h1>
        <button type="button" class="icon-btn only-mobile" data-sheet-toggle aria-label="Search and filters"><x-icon name="search"/></button>
    </header>

    @if ($hasFilter)
        <div class="active-filters">
            @if (! empty($filters['q'])) <span class="chip on">“{{ $filters['q'] }}”</span> @endif
            @if (! empty($filters['tag'])) <span class="chip on">#{{ ltrim($filters['tag'], '#') }}</span> @endif
            @if (! empty($filters['starred'])) <span class="chip on">★ starred</span> @endif
            @if (! empty($filters['due'])) <span class="chip on">⏰ due dates, soonest first</span> @endif
            @if (! empty($filters['year']) || ! empty($filters['month']) || ! empty($filters['day']))
                <span class="chip on">
                    {{ trim(($filters['day'] ?? '').' '.(! empty($filters['month']) ? date('M', mktime(0, 0, 0, $filters['month'], 1)) : '').' '.($filters['year'] ?? '')) }}
                </span>
            @endif
            <a class="chip" href="{{ route('notes.index') }}">Clear</a>
        </div>
    @endif

    @if ($errors->any())
        <div class="alert error">@foreach ($errors->all() as $error)<p>{{ $error }}</p>@endforeach</div>
    @endif

    @include('partials.composer', [
        'action' => route('notes.store'),
        'placeholder' => "What's on your mind? Use #tags to organise…",
        'schedule' => true,
        'submit' => 'Save note',
    ])

    <section id="feed" class="feed">
        @include('notes._list')
    </section>
@endsection
