<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\HtmlString;
use App\Support\Sharing;

class Note extends Model
{
    protected $fillable = ['user_id', 'parent_id', 'body', 'is_starred', 'is_pinned', 'remind_at', 'due_at', 'visibility', 'workspace_id'];

    /** A note is private unless it is posted to the Public feed or a workspace. */
    protected $attributes = ['visibility' => 'private'];

    protected function casts(): array
    {
        return [
            'is_starred' => 'boolean',
            'is_pinned' => 'boolean',
            'remind_at' => 'datetime',
            'reminded_at' => 'datetime',
            'due_at' => 'datetime',
            'due_notified_at' => 'datetime',
        ];
    }

    /** #hashtag: not glued to a word, "&" (HTML entity) or "/" (URL fragment). */
    private const TAG_PATTERN = '(?<![\p{L}\p{N}_&\/])#([\p{L}\p{N}_]{1,40})';

    /* ---------------------------------------------------------------- relations */

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function replies(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id')->oldest()->oldest('id');
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(Attachment::class)->orderBy('sort')->orderBy('id');
    }

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(Tag::class);
    }

    /* ------------------------------------------------------------------- scopes */

    /**
     * Everything $user is allowed to read:
     *  - their own private notes
     *  - every public post
     *  - posts of workspaces they belong to (admins: every workspace)
     */
    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        return $query->where(function (Builder $w) use ($user) {
            $w->where(fn (Builder $own) => $own->where('visibility', 'private')->where('user_id', $user->id))
                ->orWhere('visibility', 'public')
                ->orWhere(function (Builder $ws) use ($user) {
                    $ws->where('visibility', 'workspace');
                    if (! $user->is_admin) {
                        $ws->whereIn('workspace_id', $user->workspaceIds());
                    }
                });
        });
    }

    /** Case-insensitive search in the note itself or in any of its replies. */
    public function scopeSearch(Builder $query, string $term): Builder
    {
        // "!" is the LIKE escape character so that user-typed % and _ are literal.
        $like = '%'.str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $term).'%';

        return $query->where(function (Builder $w) use ($like) {
            $w->whereRaw("body like ? escape '!'", [$like])
                ->orWhereHas('replies', fn (Builder $r) => $r->whereRaw("body like ? escape '!'", [$like]));
        });
    }

    /** Notes (or notes having a reply) tagged with #$name. */
    public function scopeWithTag(Builder $query, string $name): Builder
    {
        $byName = fn (Builder $t) => $t->where('name', mb_strtolower(ltrim($name, '#')));

        return $query->where(function (Builder $w) use ($byName) {
            $w->whereHas('tags', $byName)
                ->orWhereHas('replies', fn (Builder $r) => $r->whereHas('tags', $byName));
        });
    }

    /* ------------------------------------------------------------------ helpers */

    public function wasEdited(): bool
    {
        return $this->updated_at && $this->updated_at->gt($this->created_at->copy()->addMinute());
    }

    /**
     * Countdown to the due date: ['text' => 'Due in 2d 3h', 'state' => ok|warn|soon|over].
     * (public/js/app.js repeats this logic so the badge stays live without reloading.)
     */
    public function countdown(): ?array
    {
        if (! $this->due_at) {
            return null;
        }

        $secs = $this->due_at->getTimestamp() - now()->getTimestamp();
        $abs = abs($secs);
        [$d, $h, $m] = [intdiv($abs, 86400), intdiv($abs % 86400, 3600), intdiv($abs % 3600, 60)];
        $span = $d > 0 ? "{$d}d {$h}h" : ($h > 0 ? "{$h}h {$m}m" : max($m, 1).'m');

        return $secs < 0
            ? ['text' => "Overdue {$span}", 'state' => 'over']
            : ['text' => "Due in {$span}", 'state' => $secs < 86400 ? 'soon' : ($secs < 3 * 86400 ? 'warn' : 'ok')];
    }

    public function isPrivate(): bool
    {
        return $this->visibility === 'private';
    }

    /** Admin (public + workspaces) and workspace owner (their workspace) may delete other people's posts. */
    public function canModerateBy(User $user): bool
    {
        return match ($this->visibility) {
            'public' => $user->is_admin,
            'workspace' => $user->is_admin || $this->workspace?->owner_id === $user->id,
            default => false,
        };
    }

    /** Why $user may not reply to this note (null = allowed). */
    public function replyBlockReason(User $user): ?string
    {
        if ($reason = $user->pauseReason()) {
            return $reason;
        }

        return match ($this->visibility) {
            'public' => Sharing::publicReplyBlockReason($user),
            'workspace' => $this->workspace?->postBlockReason($user) ?? 'This workspace no longer exists.',
            default => $this->user_id === $user->id ? null : 'You cannot reply to this note.',
        };
    }

    /** The list this note belongs to (where "back" and "after delete" should go). */
    public function feedUrl(): string
    {
        return match ($this->visibility) {
            'public' => route('public.index'),
            'workspace' => route('workspaces.show', $this->workspace_id),
            default => route('notes.index'),
        };
    }

    /** "5m ago" for recent notes, "13 Oct 2026" for old ones. */
    public function timeLabel(): string
    {
        return $this->created_at->lt(now()->subDays(7))
            ? $this->created_at->format('j M Y')
            : $this->created_at->diffForHumans(short: true);
    }

    /** Safe HTML: escaped text + clickable links + clickable #tags + line breaks. */
    public function renderBody(bool $linkTags = true): HtmlString
    {
        $safe = e($this->body);

        // One pass, so a "#fragment" inside a URL is never mistaken for a tag.
        $html = preg_replace_callback(
            '~(https?://(?:(?!&quot;|&#039;|&lt;|&gt;)\S)+)|'.self::TAG_PATTERN.'~u',
            function (array $m) use ($linkTags) {
                if ($m[1] !== '') {
                    $url = $m[1];
                    $trail = preg_match('/[.,;:!?)]+$/', $url, $t) ? $t[0] : '';
                    $url = substr($url, 0, strlen($url) - strlen($trail));

                    return '<a href="'.$url.'" target="_blank" rel="noopener noreferrer nofollow">'.$url.'</a>'.$trail;
                }

                // #tags are a private-notes feature; in shared feeds they stay plain (highlighted) text.
                if (! $linkTags) {
                    return '<span class="tag">#'.$m[2].'</span>';
                }

                return '<a class="tag" href="'.e(route('notes.index', ['tag' => mb_strtolower($m[2])])).'">#'.$m[2].'</a>';
            },
            $safe
        );

        return new HtmlString(nl2br($html ?? $safe));
    }

    /** @return string[] lower-cased unique hashtags in $body */
    public static function extractTags(string $body): array
    {
        $body = preg_replace('~https?://\S+~i', ' ', $body) ?? $body;
        preg_match_all('~'.self::TAG_PATTERN.'~u', $body, $m);

        return array_values(array_unique(array_map('mb_strtolower', $m[1])));
    }

    public function syncTags(): void
    {
        if (! $this->isPrivate()) {
            $this->tags()->detach();

            return;
        }

        $ids = collect(self::extractTags($this->body))
            ->map(fn (string $name) => Tag::firstOrCreate(['user_id' => $this->user_id, 'name' => $name])->id);

        $this->tags()->sync($ids->all());
    }

    /** Flip a boolean flag without touching updated_at (so it doesn't show "edited"). */
    public function toggleFlag(string $column): bool
    {
        $this->timestamps = false;
        $this->{$column} = ! $this->{$column};
        $this->save();

        return $this->{$column};
    }

    /** Delete this note, its replies, their attachment rows AND the files on disk. */
    public function deleteWithMedia(): void
    {
        $ids = $this->replies()->pluck('id')->push($this->id)->all();
        $paths = Attachment::whereIn('note_id', $ids)->pluck('path')->all();

        DB::transaction(function () use ($ids) {
            DB::table('note_tag')->whereIn('note_id', $ids)->delete();
            Attachment::whereIn('note_id', $ids)->delete();
            self::whereIn('id', $ids)->delete();
        });

        Storage::disk('local')->delete($paths);
    }
}
