{{-- One note or reply. Needs $note (with user + attachments loaded). Optional: $canModerate. --}}
@php
    $isReply = (bool) $note->parent_id;
    $isMine = $note->user_id === auth()->id();
    $private = $note->isPrivate();
    $canModerate = $canModerate ?? false;
@endphp
<article class="note @if ($isReply) is-reply @endif" id="note-{{ $note->id }}">
    <x-avatar :user="$note->user"/>
    <div class="note-main">
        <div class="note-head">
            <strong>{{ $note->user->name }}</strong>
            <a class="time" href="{{ route('notes.show', $isReply ? $note->parent_id : $note) }}#note-{{ $note->id }}"
               title="{{ $note->created_at->format('D, j M Y H:i') }}">· {{ $note->timeLabel() }}</a>
            @if ($note->wasEdited()) <span class="muted">· edited</span> @endif
            @if ($note->is_pinned) <span class="badge"><x-icon name="pin"/> Pinned</span> @endif
        </div>

        @if ($note->body !== '')
            <div class="note-body">{{ $note->renderBody($private) }}</div>
        @endif

        @include('notes._attachments')

        @if ($private && ! $isReply && ($note->due_at || $note->remind_at))
            <div class="schedule-badges">
                @if ($note->due_at)
                    @php $cd = $note->countdown(); @endphp
                    <span class="due {{ $cd['state'] }}" data-due="{{ $note->due_at->toIso8601String() }}" title="Due {{ $note->due_at->format('D, j M Y H:i') }}">
                        <x-icon name="clock"/> <b>{{ $cd['text'] }}</b> <span class="due-at">· {{ $note->due_at->format('j M, H:i') }}</span>
                    </span>
                @endif
                @if ($note->remind_at)
                    <span class="remind" title="Reminder email {{ $note->remind_at->format('D, j M Y H:i') }}">
                        <x-icon name="bell"/> {{ $note->reminded_at ? 'Reminded' : 'Reminder' }} {{ $note->remind_at->format('j M, H:i') }}
                    </span>
                @endif
            </div>
        @endif

        <div class="actions">
            @unless ($isReply)
                <a class="act" href="{{ route('notes.show', $note) }}" aria-label="Open thread / reply">
                    <x-icon name="reply"/> <span>{{ $note->replies_count ?? $note->replies->count() }}</span>
                </a>
                @if ($private)
                    <form method="POST" action="{{ route('notes.star', $note) }}" data-toggle>
                        @csrf
                        <button class="act star @if ($note->is_starred) is-on @endif" aria-pressed="{{ $note->is_starred ? 'true' : 'false' }}" aria-label="Star"><x-icon name="star"/></button>
                    </form>
                    <form method="POST" action="{{ route('notes.pin', $note) }}" data-toggle>
                        @csrf
                        <button class="act pin @if ($note->is_pinned) is-on @endif" aria-pressed="{{ $note->is_pinned ? 'true' : 'false' }}" aria-label="Pin"><x-icon name="pin"/></button>
                    </form>
                @endif
            @endunless
            @if ($isMine)
                <a class="act" href="{{ route('notes.edit', $note) }}" aria-label="Edit"><x-icon name="edit"/></a>
                <form method="POST" action="{{ route('notes.destroy', $note) }}" data-confirm="Delete this {{ $isReply ? 'reply' : 'post (and its replies)' }}?">
                    @csrf
                    @method('DELETE')
                    <button class="act danger" aria-label="Delete"><x-icon name="trash"/></button>
                </form>
            @elseif ($canModerate)
                <form method="POST" action="{{ route('notes.moderate', $note) }}" data-confirm="Remove this {{ $isReply ? 'reply' : 'post (and its replies)' }} written by {{ $note->user->name }}?">
                    @csrf
                    @method('DELETE')
                    <button class="act danger" aria-label="Remove as moderator" title="Remove as moderator"><x-icon name="shield"/><span>Remove</span></button>
                </form>
            @endif
        </div>

        @if ($private && ! $isReply && $note->relationLoaded('tags') && $note->tags->isNotEmpty())
            <div class="note-tags">
                @foreach ($note->tags as $tag)
                    <a class="chip" href="{{ route('notes.index', ['tag' => $tag->name]) }}">#{{ $tag->name }}</a>
                @endforeach
            </div>
        @endif
    </div>
</article>
