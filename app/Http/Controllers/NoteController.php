<?php

namespace App\Http\Controllers;

use App\Models\Note;
use App\Support\NoteWriter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * "My notes" (private) plus everything that works on any single note/thread:
 * thread page, replies, edit, delete, moderation.
 *
 * Route parameters:
 *   {note}   -> one of MY notes (edit / update / destroy / star / pin)
 *   {thread} -> any note I am allowed to read (thread page, reply, moderate)
 * Both bindings are defined in AppServiceProvider.
 */
class NoteController extends Controller
{
    /* ------------------------------------------------------------------- feed */

    public function index(Request $request): View
    {
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'tag' => ['nullable', 'string', 'max:41'],
            'starred' => ['nullable', 'boolean'],
            'due' => ['nullable', 'boolean'],
            'year' => ['nullable', 'integer', 'between:2000,2100'],
            'month' => ['nullable', 'integer', 'between:1,12'],
            'day' => ['nullable', 'integer', 'between:1,31'],
        ]);

        $user = $request->user();

        $notes = $user->notes()
            ->where('visibility', 'private')
            ->whereNull('parent_id')
            ->with(['user', 'attachments', 'tags'])
            ->withCount('replies')
            ->when($filters['q'] ?? null, fn ($q, $v) => $q->search($v))
            ->when($filters['tag'] ?? null, fn ($q, $v) => $q->withTag($v))
            ->when($filters['starred'] ?? null, fn ($q) => $q->where('is_starred', true))
            ->when($filters['due'] ?? null, fn ($q) => $q->whereNotNull('due_at')->orderBy('due_at'))
            ->when($filters['year'] ?? null, fn ($q, $v) => $q->whereYear('created_at', $v))
            ->when($filters['month'] ?? null, fn ($q, $v) => $q->whereMonth('created_at', $v))
            ->when($filters['day'] ?? null, fn ($q, $v) => $q->whereDay('created_at', $v))
            ->orderByDesc('is_pinned')
            ->latest()
            ->latest('id')
            ->simplePaginate(config('catatan.per_page'))
            ->withQueryString();

        // "Load more" asks for just the next batch of cards.
        if ($request->ajax()) {
            return view('notes._list', ['notes' => $notes]);
        }

        $firstNote = $user->notes()->where('visibility', 'private')->min('created_at');
        $years = range(now()->year, $firstNote ? Carbon::parse($firstNote)->year : now()->year);

        $tags = $user->tags()
            ->whereHas('notes')
            ->withCount('notes')
            ->orderByDesc('notes_count')
            ->orderBy('name')
            ->limit(30)
            ->get();

        return view('notes.index', [
            'notes' => $notes,
            'filters' => $filters,
            'years' => $years,
            'tags' => $tags,
        ]);
    }

    /* ----------------------------------------------------------- create / read */

    public function store(Request $request): RedirectResponse
    {
        $request->validate(NoteWriter::rules() + $this->scheduleRules());

        NoteWriter::create($request->user(), (string) $request->input('body'), $request->file('files', []), [
            'remind_at' => $this->parseWhen($request, 'remind_at'),
            'due_at' => $this->parseWhen($request, 'due_at'),
        ]);

        return redirect()->route('notes.index')->with('status', 'Note saved.');
    }

    public function show(Request $request, Note $thread): View|RedirectResponse
    {
        if ($thread->parent_id) {
            return redirect()->to(route('notes.show', $thread->parent_id).'#note-'.$thread->id);
        }

        $thread->load(['user', 'attachments', 'tags', 'workspace', 'replies.user', 'replies.attachments']);

        return view('notes.show', [
            'note' => $thread,
            'canModerate' => $thread->canModerateBy($request->user()),
            'replyBlock' => $thread->replyBlockReason($request->user()),
        ]);
    }

    public function reply(Request $request, Note $thread): RedirectResponse
    {
        if ($reason = $thread->replyBlockReason($request->user())) {
            throw ValidationException::withMessages(['body' => $reason]);
        }

        $request->validate(NoteWriter::rules());

        $rootId = $thread->parent_id ?? $thread->id; // threads are one level deep
        $reply = NoteWriter::create($request->user(), (string) $request->input('body'), $request->file('files', []), [
            'parent_id' => $rootId,
            'visibility' => $thread->visibility,
            'workspace_id' => $thread->workspace_id,
        ]);

        return redirect()->to(route('notes.show', $rootId).'#note-'.$reply->id)->with('status', 'Reply added.');
    }

    /* ------------------------------------------------------------ edit / delete */

    public function edit(Note $note): View
    {
        $note->load('attachments');

        return view('notes.edit', ['note' => $note]);
    }

    public function update(Request $request, Note $note): RedirectResponse
    {
        $max = (int) config('catatan.max_attachments');
        $withSchedule = ! $note->parent_id && $note->isPrivate();

        $request->validate(NoteWriter::rules() + ['remove' => ['nullable', 'array'], 'remove.*' => ['integer']]
            + ($withSchedule ? $this->scheduleRules() : []));

        $remove = $note->attachments()->whereIn('id', (array) $request->input('remove', []))->get();
        $newFiles = $request->file('files', []);
        $remaining = $note->attachments()->count() - $remove->count();

        if ($remaining + count($newFiles) > $max) {
            throw ValidationException::withMessages(['files' => "A post can have at most {$max} attachments."]);
        }
        if (blank($request->input('body')) && $remaining + count($newFiles) === 0) {
            throw ValidationException::withMessages(['body' => 'A note needs some text or at least one attachment.']);
        }

        $remindAt = $withSchedule ? $this->parseWhen($request, 'remind_at', $note->remind_at) : null;
        $dueAt = $withSchedule ? $this->parseWhen($request, 'due_at', $note->due_at) : null;

        DB::transaction(function () use ($request, $note, $remove, $newFiles, $remindAt, $dueAt, $withSchedule) {
            $changes = ['body' => (string) $request->input('body')];

            // Reminder / due date (private root notes only). Changing a time re-arms its email.
            if ($withSchedule) {
                if (! $this->sameMinute($note->remind_at, $remindAt)) {
                    $changes += ['remind_at' => $remindAt, 'reminded_at' => null];
                }
                if (! $this->sameMinute($note->due_at, $dueAt)) {
                    $changes += ['due_at' => $dueAt, 'due_notified_at' => null];
                }
            }

            $note->forceFill($changes)->save();
            $note->syncTags();

            foreach ($remove as $attachment) {
                Storage::disk('local')->delete($attachment->path);
                $attachment->delete();
            }

            NoteWriter::storeFiles($note, $newFiles, (int) $note->attachments()->max('sort') + 1);
        });

        return redirect()->to(route('notes.show', $note->parent_id ?? $note->id))->with('status', 'Note updated.');
    }

    /** Delete one of my own notes / replies. */
    public function destroy(Note $note): RedirectResponse
    {
        return $this->remove($note, 'Note deleted.');
    }

    /** Delete someone else's post: site admin (public + workspaces) or workspace owner (their workspace). */
    public function moderate(Request $request, Note $thread): RedirectResponse
    {
        $thread->loadMissing('workspace');
        abort_unless($thread->canModerateBy($request->user()), 403);

        return $this->remove($thread, 'Removed.');
    }

    private function remove(Note $note, string $message): RedirectResponse
    {
        $target = $note->parent_id ? route('notes.show', $note->parent_id) : $note->feedUrl();
        $note->deleteWithMedia();

        return redirect()->to($target)->with('status', $message);
    }

    /* --------------------------------------------------------------- star / pin */

    public function toggleStar(Request $request, Note $note): JsonResponse|RedirectResponse
    {
        abort_unless($note->isPrivate(), 404);

        return $this->toggled($request, $note->toggleFlag('is_starred'));
    }

    public function togglePin(Request $request, Note $note): JsonResponse|RedirectResponse
    {
        abort_unless($note->isPrivate(), 404);

        return $this->toggled($request, $note->toggleFlag('is_pinned'));
    }

    private function toggled(Request $request, bool $on): JsonResponse|RedirectResponse
    {
        return $request->expectsJson() ? response()->json(['on' => $on]) : back();
    }

    /* ------------------------------------------------------------------ helpers */

    private function scheduleRules(): array
    {
        return ['remind_at' => ['nullable', 'date'], 'due_at' => ['nullable', 'date']];
    }

    /**
     * Read a <input type="datetime-local"> value (no timezone) as app-timezone time.
     * A reminder must be in the future, unless it is the value the note already has.
     */
    private function parseWhen(Request $request, string $field, ?Carbon $current = null): ?Carbon
    {
        $raw = $request->input($field);
        if (blank($raw)) {
            return null;
        }

        $when = Carbon::parse($raw, config('app.timezone'));

        if ($field === 'remind_at' && $when->isPast() && ! $this->sameMinute($current, $when)) {
            throw ValidationException::withMessages([$field => 'The reminder time must be in the future.']);
        }

        return $when;
    }

    private function sameMinute(?Carbon $a, ?Carbon $b): bool
    {
        return $a?->format('Y-m-d H:i') === $b?->format('Y-m-d H:i');
    }
}
