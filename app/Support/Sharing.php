<?php

namespace App\Support;

use App\Models\Note;
use App\Models\Setting;
use App\Models\User;

/** Rules for posting to the Public feed (admin kill switch, per-user block, daily limit). */
final class Sharing
{
    public const KILL_SWITCH = 'public_sharing_enabled';
    public const DAILY_LIMIT = 'public_daily_limit';

    public static function publicEnabled(): bool
    {
        return Setting::bool(self::KILL_SWITCH, true);
    }

    /** 0 = unlimited. */
    public static function dailyLimit(): int
    {
        return max(0, Setting::int(self::DAILY_LIMIT, 0));
    }

    public static function postsToday(User $user): int
    {
        return Note::where('user_id', $user->id)
            ->where('visibility', 'public')
            ->whereNull('parent_id')
            ->where('created_at', '>=', now()->startOfDay())
            ->count();
    }

    /** Why this user may not start a public post right now (null = allowed). */
    public static function publicPostBlockReason(User $user): ?string
    {
        if ($reason = self::publicReplyBlockReason($user)) {
            return $reason;
        }

        $limit = self::dailyLimit();
        if ($limit > 0 && self::postsToday($user) >= $limit) {
            return "You reached today's limit of {$limit} public post".($limit === 1 ? '' : 's').'. It resets at midnight.';
        }

        return null;
    }

    /** Replies are not counted against the daily limit, but the kill switch and per-user block still apply. */
    public static function publicReplyBlockReason(User $user): ?string
    {
        if ($reason = $user->pauseReason()) {
            return $reason;
        }

        if (! self::publicEnabled()) {
            return 'Public sharing is switched off by the admin for now.';
        }
        if ($user->public_blocked) {
            return 'The admin has turned off public sharing for your account.';
        }

        return null;
    }

    /** Posts left today (null = unlimited). */
    public static function postsLeftToday(User $user): ?int
    {
        $limit = self::dailyLimit();

        return $limit > 0 ? max(0, $limit - self::postsToday($user)) : null;
    }
}
