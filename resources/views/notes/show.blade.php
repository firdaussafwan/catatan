@extends('layouts.app')
@section('title', 'Thread')
@section('compose-url', route('notes.show', $note).'#reply')

@section('content')
    <header class="topbar">
        <a class="icon-btn" href="{{ $note->feedUrl() }}" aria-label="Back"><x-icon name="back"/></a>
        <h1>Thread</h1>
        @if ($note->visibility === 'workspace' && $note->workspace)
            <small class="muted">· {{ $note->workspace->name }}</small>
        @elseif ($note->visibility === 'public')
            <small class="muted">· Public</small>
        @endif
    </header>

    @if ($errors->any())
        <div class="alert error">@foreach ($errors->all() as $error)<p>{{ $error }}</p>@endforeach</div>
    @endif

    @include('notes._card', ['note' => $note])

    @if ($replyBlock)
        <div class="composer" id="reply"><div class="notice"><x-icon name="shield"/> <span>{{ $replyBlock }}</span></div></div>
    @else
        <form class="composer reply-composer" method="POST" action="{{ route('notes.reply', $note) }}" enctype="multipart/form-data" id="reply">
            @csrf
            <div class="composer-main">
                <textarea name="body" class="autosize" rows="1" maxlength="{{ config('catatan.max_body') }}"
                          placeholder="Add to this thread…" aria-label="Reply">{{ old('body') }}</textarea>
                <div class="previews" data-previews></div>
                <div class="composer-bar">
                    <label class="icon-btn" title="Attach photos, videos or files" aria-label="Attach photos, videos or files">
                        <x-icon name="attach"/>
                        <input type="file" name="files[]" multiple hidden data-file data-max="{{ config('catatan.max_attachments') }}">
                    </label>
                    <button class="btn primary" type="submit">Reply</button>
                </div>
            </div>
        </form>
    @endif

    <section class="feed">
        @foreach ($note->replies as $reply)
            @include('notes._card', ['note' => $reply])
        @endforeach
    </section>
@endsection
