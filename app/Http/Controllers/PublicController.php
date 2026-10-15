<?php

namespace App\Http\Controllers;

use App\Support\NoteWriter;
use App\Models\Note;
use App\Support\Sharing;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/** The Public feed: every signed-in user can read and post (unless the admin says otherwise). */
class PublicController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();
        $q = trim((string) $request->query('q', ''));

        $notes = Note::query()
            ->where('visibility', 'public')
            ->whereNull('parent_id')
            ->with(['user', 'attachments'])
            ->withCount('replies')
            ->when($q !== '', fn ($query) => $query->search(mb_substr($q, 0, 100)))
            ->latest()->latest('id')
            ->simplePaginate(config('catatan.per_page'))
            ->withQueryString();

        if ($request->ajax()) {
            return view('notes._list', ['notes' => $notes, 'canModerate' => $user->is_admin]);
        }

        return view('public.index', [
            'notes' => $notes,
            'q' => $q,
            'canModerate' => $user->is_admin,
            'blockReason' => Sharing::publicPostBlockReason($user),
            'postsLeft' => Sharing::postsLeftToday($user),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $user = $request->user();

        if ($reason = Sharing::publicPostBlockReason($user)) {
            throw ValidationException::withMessages(['body' => $reason]);
        }

        $request->validate(NoteWriter::rules());

        NoteWriter::create($user, (string) $request->input('body'), $request->file('files', []), [
            'visibility' => 'public',
        ]);

        return redirect()->route('public.index')->with('status', 'Shared publicly.');
    }
}
