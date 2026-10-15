<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

#[Fillable(['name', 'email', 'password'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_admin' => 'boolean',
            'is_suspended' => 'boolean',
            'public_blocked' => 'boolean',
            'is_paused' => 'boolean',
            'suspended_until' => 'datetime',
        ];
    }

    /** Authenticated URL of the profile picture; the path changes on every upload, so it busts the cache. */
    public function avatarUrl(): ?string
    {
        return $this->avatar_path
            ? route('avatar.show', ['user' => $this->id, 'v' => substr(md5($this->avatar_path), 0, 8)])
            : null;
    }

    /** Workspaces this user is a member of. */
    public function workspaces(): BelongsToMany
    {
        return $this->belongsToMany(Workspace::class, 'workspace_members')->withPivot(['role', 'is_restricted']);
    }

    /** @return int[] ids of the workspaces I belong to */
    public function workspaceIds(): array
    {
        return WorkspaceMember::where('user_id', $this->id)->pluck('workspace_id')->all();
    }

    public function notes(): HasMany
    {
        return $this->hasMany(Note::class);
    }

    public function tags(): HasMany
    {
        return $this->hasMany(Tag::class);
    }

    /* ------------------------------------------------------------- account states */

    /** Suspended = can not sign in. With a date it lifts itself; without one the admin must lift it. */
    public function isSuspendedNow(): bool
    {
        return $this->is_suspended && (! $this->suspended_until || $this->suspended_until->isFuture());
    }

    public function suspensionMessage(): string
    {
        return $this->suspended_until
            ? 'This account is suspended until '.$this->suspended_until->format('j M Y, H:i').'. Contact the admin if you think this is a mistake.'
            : 'This account has been suspended. Contact the admin.';
    }

    /** Paused = read-only: can sign in and read, can not post, reply, edit or upload. */
    public function pauseReason(): ?string
    {
        return $this->is_paused
            ? 'Your account is paused by the admin: you can read, but you can not post, reply or upload for now.'
            : null;
    }

    /** Permanently remove this account with everything it posted (notes, replies, files, picture, sessions). */
    public function deleteWithData(): void
    {
        // Roots first (their replies, even other people's, go with them), then replies left under other people's posts.
        foreach (Note::where('user_id', $this->id)->whereNull('parent_id')->get() as $note) {
            $note->deleteWithMedia();
        }
        foreach (Note::where('user_id', $this->id)->get() as $note) {
            $note->deleteWithMedia();
        }

        if ($this->avatar_path) {
            Storage::disk('local')->delete($this->avatar_path);
        }

        DB::table('sessions')->where('user_id', $this->id)->delete();
        DB::table('password_reset_tokens')->where('email', $this->email)->delete();

        $this->delete(); // database cascades remove tags, workspace memberships and join requests
    }
}
