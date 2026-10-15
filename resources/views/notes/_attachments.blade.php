{{-- Images + videos are shown inline (like a reel); every other file is a download card. Needs $note. --}}
@php
    $media = $note->attachments->filter(fn ($a) => $a->isImage() || $a->isVideo())->values();
    $files = $note->attachments->reject(fn ($a) => $a->isImage() || $a->isVideo());
@endphp

@if ($media->isNotEmpty())
    <div class="media n{{ min($media->count(), 5) }}">
        @foreach ($media as $a)
            @if ($a->isImage())
                <a href="{{ $a->url() }}" data-lightbox><img src="{{ $a->url() }}" alt="{{ $a->displayName() }}" loading="lazy"></a>
            @else
                <div class="clip">
                    <video src="{{ $a->url() }}" controls playsinline muted loop preload="metadata" data-reel aria-label="{{ $a->displayName() }}"></video>
                </div>
            @endif
        @endforeach
    </div>
@endif

@if ($files->isNotEmpty())
    <ul class="files">
        @foreach ($files as $a)
            <li class="file">
                <x-icon name="file"/>
                <span class="file-name" title="{{ $a->displayName() }}">{{ $a->displayName() }}</span>
                <small>{{ $a->humanSize() }}</small>
                <a class="btn sm" href="{{ $a->downloadUrl() }}" download><x-icon name="download"/> Download</a>
            </li>
        @endforeach
    </ul>
@endif
