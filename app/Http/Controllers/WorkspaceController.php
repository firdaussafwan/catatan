<?php

namespace App\Http\Controllers;

use App\Models\Note;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceMember;
use App\Models\WorkspaceRequest;
use App\Support\NoteWriter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Workspaces (groups). Only an admin can create one; the owner then runs it:
 * share the invite link or approve join requests, restrict / kick members, delete messages.
 */
class WorkspaceController extends Controller
{
    /* ------------------------------------------------------------------ lists */

    public function index(Request $request): View
    {
        $user = $request->user();
        $mine = $user->is_admin
            ? Workspace::query()
            : Workspace::whereIn('id', $user->workspaceIds());

        $mine = $mine->withCount('members')->with('owner')->orderBy('name')->get();
        $pendingCounts = WorkspaceRequest::whereIn('workspace_id', $mine->pluck('id'))
            ->selectRaw('workspace_id, count(*) as c')->groupBy('workspace_id')->pluck('c', 'workspace_id');

        // Directory: listed workspaces I am not in yet (I can ask to join).
        $discover = Workspace::where('is_listed', true)
            ->whereNotIn('id', $user->workspaceIds())
            ->withCount('members')->with('owner')->orderBy('name')->get();
        $requested = WorkspaceRequest::where('user_id', $user->id)->pluck('workspace_id')->all();

        return view('workspaces.index', [
            'mine' => $mine,
            'pending' => $pendingCounts,
            'discover' => $discover,
            'requested' => $requested,
            'candidates' => $user->is_admin ? User::where('is_suspended', false)->orderBy('name')->get() : collect(),
        ]);
    }

    /** Admin only: open a workspace and pick its owner. */
    public function store(Request $request): RedirectResponse
    {
        abort_unless($request->user()->is_admin, 403);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:80'],
            'description' => ['nullable', 'string', 'max:300'],
            'owner_id' => ['required', 'exists:users,id'],
        ]);

        $workspace = DB::transaction(function () use ($data) {
            $workspace = Workspace::create($data);
            WorkspaceMember::create(['workspace_id' => $workspace->id, 'user_id' => $data['owner_id'], 'role' => 'owner']);

            return $workspace;
        });

        return redirect()->route('workspaces.manage', $workspace)->with('status', 'Workspace created. Share the invite link with your people.');
    }

    /* ------------------------------------------------------------------- feed */

    public function show(Request $request, Workspace $workspace): View
    {
        $user = $request->user();
        abort_unless($workspace->canBeReadBy($user), 404);

        $notes = Note::query()
            ->where('visibility', 'workspace')->where('workspace_id', $workspace->id)
            ->whereNull('parent_id')
            ->with(['user', 'attachments'])
            ->withCount('replies')
            ->latest()->latest('id')
            ->simplePaginate(config('catatan.per_page'));

        $canModerate = $workspace->canBeManagedBy($user);

        if ($request->ajax()) {
            return view('notes._list', ['notes' => $notes, 'canModerate' => $canModerate]);
        }

        return view('workspaces.show', [
            'workspace' => $workspace->loadCount('members'),
            'notes' => $notes,
            'canModerate' => $canModerate,
            'blockReason' => $workspace->postBlockReason($user),
        ]);
    }

    public function post(Request $request, Workspace $workspace): RedirectResponse
    {
        $user = $request->user();
        abort_unless($workspace->canBeReadBy($user), 404);

        if ($reason = $workspace->postBlockReason($user)) {
            throw ValidationException::withMessages(['body' => $reason]);
        }

        $request->validate(NoteWriter::rules());

        NoteWriter::create($user, (string) $request->input('body'), $request->file('files', []), [
            'visibility' => 'workspace',
            'workspace_id' => $workspace->id,
        ]);

        return redirect()->route('workspaces.show', $workspace)->with('status', 'Posted.');
    }

    /* ----------------------------------------------------------------- joining */

    /** Landing page of an invite link. */
    public function joinShow(Request $request, string $token): View|RedirectResponse
    {
        $workspace = Workspace::where('join_token', $token)->where('invite_enabled', true)->firstOrFail();

        if ($workspace->membershipOf($request->user())) {
            return redirect()->route('workspaces.show', $workspace);
        }

        return view('workspaces.join', ['workspace' => $workspace->loadCount('members')->load('owner')]);
    }

    public function joinStore(Request $request, string $token): RedirectResponse
    {
        $workspace = Workspace::where('join_token', $token)->where('invite_enabled', true)->firstOrFail();

        $this->addMember($workspace, $request->user());

        return redirect()->route('workspaces.show', $workspace)->with('status', "You joined {$workspace->name}.");
    }

    /** Ask the owner to let me in (for listed workspaces). */
    public function requestJoin(Request $request, Workspace $workspace): RedirectResponse
    {
        abort_unless($workspace->is_listed, 404);

        if (! $workspace->membershipOf($request->user())) {
            WorkspaceRequest::firstOrCreate(['workspace_id' => $workspace->id, 'user_id' => $request->user()->id]);
        }

        return back()->with('status', 'Request sent. The owner will review it.');
    }

    public function cancelRequest(Request $request, Workspace $workspace): RedirectResponse
    {
        WorkspaceRequest::where('workspace_id', $workspace->id)->where('user_id', $request->user()->id)->delete();

        return back()->with('status', 'Request cancelled.');
    }

    public function leave(Request $request, Workspace $workspace): RedirectResponse
    {
        $member = $workspace->membershipOf($request->user());
        abort_unless($member, 404);

        if ($member->isOwner()) {
            throw ValidationException::withMessages(['leave' => 'The owner can not leave. An admin can delete the workspace instead.']);
        }

        $member->delete();

        return redirect()->route('workspaces.index')->with('status', "You left {$workspace->name}.");
    }

    /* -------------------------------------------------------------- management */

    public function manage(Request $request, Workspace $workspace): View
    {
        $this->authorizeManage($request, $workspace);

        return view('workspaces.manage', [
            'workspace' => $workspace,
            'members' => $workspace->members()->with('user')->orderByDesc('role')->orderBy('id')->get(),
            'requests' => $workspace->requests()->with('user')->oldest()->get(),
        ]);
    }

    public function update(Request $request, Workspace $workspace): RedirectResponse
    {
        $this->authorizeManage($request, $workspace);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:80'],
            'description' => ['nullable', 'string', 'max:300'],
        ]);

        $workspace->update($data + [
            'invite_enabled' => $request->boolean('invite_enabled'),
            'is_listed' => $request->boolean('is_listed'),
        ]);

        return back()->with('status', 'Workspace updated.');
    }

    /** Invalidate the old invite link and make a new one. */
    public function regenerateLink(Request $request, Workspace $workspace): RedirectResponse
    {
        $this->authorizeManage($request, $workspace);
        $workspace->regenerateToken();

        return back()->with('status', 'New invite link created. The old one no longer works.');
    }

    /** Delete every message (and file) but keep the workspace and its members. */
    public function clear(Request $request, Workspace $workspace): RedirectResponse
    {
        $this->authorizeManage($request, $workspace);

        Note::where('workspace_id', $workspace->id)->whereNull('parent_id')
            ->get()->each(fn (Note $note) => $note->deleteWithMedia());

        return back()->with('status', 'Chat cleared. Members were not removed.');
    }

    public function destroy(Request $request, Workspace $workspace): RedirectResponse
    {
        $this->authorizeManage($request, $workspace);
        $workspace->deleteWithMedia();

        return redirect()->route('workspaces.index')->with('status', 'Workspace and all its messages were deleted.');
    }

    /** Restricted = can read, can not post or reply. */
    public function toggleRestrict(Request $request, Workspace $workspace, WorkspaceMember $member): RedirectResponse
    {
        $this->authorizeManage($request, $workspace);
        $this->ownMember($workspace, $member);

        if ($member->isOwner()) {
            throw ValidationException::withMessages(['member' => 'The owner can not be restricted.']);
        }

        $member->update(['is_restricted' => ! $member->is_restricted]);

        return back()->with('status', $member->is_restricted ? "{$member->user->name} is restricted." : "{$member->user->name} can post again.");
    }

    /** Remove a member. Optionally wipe everything they posted here. */
    public function kick(Request $request, Workspace $workspace, WorkspaceMember $member): RedirectResponse
    {
        $this->authorizeManage($request, $workspace);
        $this->ownMember($workspace, $member);

        if ($member->isOwner()) {
            throw ValidationException::withMessages(['member' => 'The owner can not be removed.']);
        }

        if ($request->boolean('delete_messages')) {
            Note::where('workspace_id', $workspace->id)->where('user_id', $member->user_id)
                ->get()->each(fn (Note $note) => $note->deleteWithMedia());
        }

        $member->delete();

        return back()->with('status', "{$member->user->name} was removed.");
    }

    public function approve(Request $request, Workspace $workspace, WorkspaceRequest $joinRequest): RedirectResponse
    {
        $this->authorizeManage($request, $workspace);
        abort_unless($joinRequest->workspace_id === $workspace->id, 404);

        $this->addMember($workspace, $joinRequest->user);

        return back()->with('status', "{$joinRequest->user->name} joined.");
    }

    public function deny(Request $request, Workspace $workspace, WorkspaceRequest $joinRequest): RedirectResponse
    {
        $this->authorizeManage($request, $workspace);
        abort_unless($joinRequest->workspace_id === $workspace->id, 404);

        $joinRequest->delete();

        return back()->with('status', 'Request declined.');
    }

    /* ----------------------------------------------------------------- helpers */

    private function authorizeManage(Request $request, Workspace $workspace): void
    {
        abort_unless($workspace->canBeManagedBy($request->user()), 404);
    }

    private function ownMember(Workspace $workspace, WorkspaceMember $member): void
    {
        abort_unless($member->workspace_id === $workspace->id, 404);
    }

    private function addMember(Workspace $workspace, User $user): void
    {
        WorkspaceMember::firstOrCreate(['workspace_id' => $workspace->id, 'user_id' => $user->id], ['role' => 'member']);
        WorkspaceRequest::where('workspace_id', $workspace->id)->where('user_id', $user->id)->delete();
    }
}
