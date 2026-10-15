{{-- Reminder + due date fields. Used by the composer and the edit page ($note is set only when editing). --}}
@php
    $remindValue = old('remind_at', isset($note) && $note->remind_at ? $note->remind_at->format('Y-m-d\TH:i') : '');
    $dueValue = old('due_at', isset($note) && $note->due_at ? $note->due_at->format('Y-m-d\TH:i') : '');
@endphp
<details class="schedule" @if ($remindValue || $dueValue || $errors->has('remind_at') || $errors->has('due_at')) open @endif>
    <summary><x-icon name="bell"/> Reminder &amp; due date</summary>

    <div class="schedule-body">
        <div class="field">
            <label for="remind_at_{{ $note->id ?? 'new' }}"><x-icon name="bell"/> Email me a reminder at</label>
            <input type="datetime-local" id="remind_at_{{ $note->id ?? 'new' }}" name="remind_at" value="{{ $remindValue }}">
            <div class="chips">
                <button type="button" class="chip" data-target="remind_at" data-preset="+1h">In 1 hour</button>
                <button type="button" class="chip" data-target="remind_at" data-preset="+3h">In 3 hours</button>
                <button type="button" class="chip" data-target="remind_at" data-preset="+1d@09:00">Tomorrow 9:00</button>
                <button type="button" class="chip" data-target="remind_at" data-preset="+1d">In 1 day</button>
                <button type="button" class="chip" data-target="remind_at" data-preset="+1w">In 1 week</button>
                <button type="button" class="chip" data-target="remind_at" data-preset="+1M">In 1 month</button>
                <button type="button" class="chip" data-target="remind_at" data-preset="clear">Clear</button>
            </div>
            @if (isset($note) && $note->reminded_at)
                <small>Already sent {{ $note->reminded_at->format('j M, H:i') }}. Pick a new time to send it again.</small>
            @endif
        </div>

        <div class="field">
            <label for="due_at_{{ $note->id ?? 'new' }}"><x-icon name="clock"/> Due date <small>(we email you 24 hours before)</small></label>
            <input type="datetime-local" id="due_at_{{ $note->id ?? 'new' }}" name="due_at" value="{{ $dueValue }}">
            <div class="chips">
                <button type="button" class="chip" data-target="due_at" data-preset="+1d@17:00">Tomorrow 17:00</button>
                <button type="button" class="chip" data-target="due_at" data-preset="+3d@17:00">In 3 days</button>
                <button type="button" class="chip" data-target="due_at" data-preset="+1w@17:00">In 1 week</button>
                <button type="button" class="chip" data-target="due_at" data-preset="+2w@17:00">In 2 weeks</button>
                <button type="button" class="chip" data-target="due_at" data-preset="+1M@17:00">In 1 month</button>
                <button type="button" class="chip" data-target="due_at" data-preset="clear">Clear</button>
            </div>
        </div>
    </div>
</details>
