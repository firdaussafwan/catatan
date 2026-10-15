{{--
  Composer used by My notes, Public and Workspaces.
  Needs: $action, $placeholder. Optional: $schedule (reminder/due fields), $submit, $blockReason (shows a notice instead).
--}}
@php $blockReason = ($blockReason ?? null) ?: auth()->user()->pauseReason(); @endphp
@if (! empty($blockReason))
    <div class="composer" id="composer">
        <div class="notice"><x-icon name="shield"/> <span>{{ $blockReason }}</span></div>
    </div>
@else
    <form id="composer" class="composer" method="POST" action="{{ $action }}" enctype="multipart/form-data">
        @csrf
        <x-avatar/>
        <div class="composer-main">
            <textarea name="body" class="autosize" rows="2" maxlength="{{ config('catatan.max_body') }}"
                      placeholder="{{ $placeholder }}" aria-label="New post">{{ old('body') }}</textarea>
            <div class="previews" data-previews></div>
            @if (! empty($schedule))
                @include('notes._schedule')
            @endif
            <div class="composer-bar">
                <label class="icon-btn" title="Attach photos, videos or files" aria-label="Attach photos, videos or files">
                    <x-icon name="attach"/>
                    <input type="file" name="files[]" multiple hidden data-file data-max="{{ config('catatan.max_attachments') }}">
                </label>
                <small class="muted hint">Ctrl/⌘ + Enter to post</small>
                <button class="btn primary" type="submit">{{ $submit ?? 'Post' }}</button>
            </div>
        </div>
    </form>
@endif
