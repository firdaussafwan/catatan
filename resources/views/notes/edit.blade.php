@extends('layouts.app')
@section('title', 'Edit')

@section('content')
    <header class="topbar">
        <a class="icon-btn" href="{{ route('notes.show', $note->parent_id ?? $note) }}" aria-label="Back"><x-icon name="back"/></a>
        <h1>Edit {{ $note->parent_id ? 'reply' : 'post' }}</h1>
    </header>

    @if ($errors->any())
        <div class="alert error">@foreach ($errors->all() as $error)<p>{{ $error }}</p>@endforeach</div>
    @endif

    <form method="POST" action="{{ route('notes.update', $note) }}" enctype="multipart/form-data" class="panel form">
        @csrf
        @method('PUT')
        <textarea name="body" class="autosize" rows="5" maxlength="{{ config('catatan.max_body') }}" aria-label="Text">{{ old('body', $note->body) }}</textarea>

        @if ($note->attachments->isNotEmpty())
            <div class="edit-images">
                @foreach ($note->attachments as $a)
                    <label class="edit-image">
                        @if ($a->isImage())
                            <img src="{{ $a->url() }}" alt="{{ $a->displayName() }}" loading="lazy">
                        @else
                            <span class="edit-file"><x-icon :name="$a->isVideo() ? 'film' : 'file'"/><small>{{ \Illuminate\Support\Str::limit($a->displayName(), 22) }}</small></span>
                        @endif
                        <span class="check"><input type="checkbox" name="remove[]" value="{{ $a->id }}"> Remove</span>
                    </label>
                @endforeach
            </div>
        @endif

        <div class="previews" data-previews></div>
        @if ($note->isPrivate() && ! $note->parent_id)
            @include('notes._schedule')
        @endif
        <div class="composer-bar">
            <label class="icon-btn" title="Attach photos, videos or files" aria-label="Attach photos, videos or files">
                <x-icon name="attach"/>
                <input type="file" name="files[]" multiple hidden data-file data-max="{{ config('catatan.max_attachments') }}">
            </label>
            <a class="btn" href="{{ route('notes.show', $note->parent_id ?? $note) }}">Cancel</a>
            <button class="btn primary" type="submit">Save changes</button>
        </div>
    </form>
@endsection
