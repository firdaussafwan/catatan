@forelse ($notes as $note)
    @include('notes._card', ['note' => $note])
@empty
    @if ($notes->currentPage() === 1)
        <div class="empty">
            <p><strong>Nothing here yet.</strong></p>
            <p class="muted">Nothing to show yet. Post something above, or loosen the filters.</p>
        </div>
    @endif
@endforelse

@if ($notes->hasMorePages())
    <button type="button" class="btn wide load-more" data-next="{{ $notes->nextPageUrl() }}">Load more</button>
@endif
