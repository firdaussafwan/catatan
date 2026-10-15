<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class Workspace extends Model
{
    protected $fillable = ['name', 'description', 'owner_id', 'join_token', 'invite_enabled', 'is_listed'];

    protected function casts(): array
    {
        return ['invite_enabled' => 'boolean', 'is_listed' => 'boolean'];
    }

    protected static function booted(): void
    {
        static::creating(function (Workspace $w) {
            $w->join_token ??= Str::random(40);
        });
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function members(): HasMany
    {
        return $this->hasMany(WorkspaceMember::class);
    }

    public function requests(): HasMany
    {
        return $this->hasMany(WorkspaceRequest::class);
    }

    public function notes(): HasMany
    {
        return $this->hasMany(Note::class)->where('visibility', 'workspace');
    }

    public function membershipOf(User $user): ?WorkspaceMember
    {
        return $this->members()->where('user_id', $user->id)->first();
    }

    /** Why $user may not post here right now (null = allowed). */
    public function postBlockReason(User $user): ?string
    {
        if ($reason = $user->pauseReason()) {
            return $reason;
        }

        $member = $this->membershipOf($user);

        if (! $member) {
            return 'Join this workspace to post.';
        }
        if ($member->is_restricted) {
            return 'The owner has restricted your account here: you can read but not post.';
        }

        return null;
    }

    /** Owner or site admin may moderate: kick, restrict, delete anyone's message, edit settings. */
    public function canBeManagedBy(User $user): bool
    {
        return $user->is_admin || $this->owner_id === $user->id;
    }

    /** Admins can look into every workspace; everyone else must be a member. */
    public function canBeReadBy(User $user): bool
    {
        return $user->is_admin || $this->members()->where('user_id', $user->id)->exists();
    }

    public function joinUrl(): string
    {
        return route('workspaces.join', $this->join_token);
    }

    public function regenerateToken(): void
    {
        $this->update(['join_token' => Str::random(40)]);
    }

    /** Delete the workspace with all its messages and their files. */
    public function deleteWithMedia(): void
    {
        $noteIds = Note::where('workspace_id', $this->id)->pluck('id')->all();
        $paths = Attachment::whereIn('note_id', $noteIds)->pluck('path')->all();

        DB::transaction(function () use ($noteIds) {
            Attachment::whereIn('note_id', $noteIds)->delete();
            Note::whereIn('id', $noteIds)->delete();
            $this->requests()->delete();
            $this->members()->delete();
            $this->delete();
        });

        Storage::disk('local')->delete($paths);
    }
}
