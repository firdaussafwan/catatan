{{-- Search + date filters + tags. Desktop: right column. Mobile: slide-up sheet. --}}
@php
    $activeFilters = collect($filters)->filter(fn ($v) => $v !== null && $v !== '' && $v !== '0');
    $today = now();
@endphp

<div class="sheet-head">
    <h2>Search & filter</h2>
    <button type="button" class="icon-btn" data-sheet-toggle aria-label="Close"><x-icon name="x"/></button>
</div>

<form method="GET" action="{{ route('notes.index') }}" class="rail-form" role="search">
    <div class="searchbox">
        <x-icon name="search"/>
        <input type="search" name="q" value="{{ $filters['q'] ?? '' }}" placeholder="Search notes…" maxlength="100" aria-label="Search notes">
    </div>

    <fieldset>
        <legend><x-icon name="calendar"/> Date</legend>
        <div class="date-row">
            <select name="year" aria-label="Year">
                <option value="">Year</option>
                @foreach ($years as $y)
                    <option value="{{ $y }}" @selected(($filters['year'] ?? null) == $y)>{{ $y }}</option>
                @endforeach
            </select>
            <select name="month" aria-label="Month">
                <option value="">Month</option>
                @foreach (range(1, 12) as $m)
                    <option value="{{ $m }}" @selected(($filters['month'] ?? null) == $m)>{{ date('M', mktime(0, 0, 0, $m, 1)) }}</option>
                @endforeach
            </select>
            <select name="day" aria-label="Day">
                <option value="">Day</option>
                @foreach (range(1, 31) as $d)
                    <option value="{{ $d }}" @selected(($filters['day'] ?? null) == $d)>{{ $d }}</option>
                @endforeach
            </select>
        </div>
        <div class="chips">
            <a class="chip" href="{{ route('notes.index', ['year' => $today->year, 'month' => $today->month, 'day' => $today->day]) }}">Today</a>
            <a class="chip" href="{{ route('notes.index', ['year' => $today->year, 'month' => $today->month]) }}">This month</a>
            <a class="chip" href="{{ route('notes.index', ['year' => $today->year]) }}">This year</a>
        </div>
    </fieldset>

    <label class="check">
        <input type="checkbox" name="starred" value="1" @checked(! empty($filters['starred']))>
        <x-icon name="star"/> Starred only
    </label>

    <label class="check">
        <input type="checkbox" name="due" value="1" @checked(! empty($filters['due']))>
        <x-icon name="clock"/> Has a due date
    </label>

    @if (! empty($filters['tag']))
        <input type="hidden" name="tag" value="{{ $filters['tag'] }}">
    @endif

    <div class="rail-actions">
        <button class="btn primary" type="submit">Apply</button>
        @if ($activeFilters->isNotEmpty())
            <a class="btn" href="{{ route('notes.index') }}">Clear</a>
        @endif
    </div>
</form>

@if ($tags->isNotEmpty())
    <section class="tags-box">
        <h3><x-icon name="tag"/> Tags</h3>
        <div class="chips">
            @foreach ($tags as $tag)
                <a class="chip @if (($filters['tag'] ?? null) === $tag->name) on @endif" href="{{ route('notes.index', ['tag' => $tag->name]) }}">#{{ $tag->name }} <small>{{ $tag->notes_count }}</small></a>
            @endforeach
        </div>
    </section>
@endif
