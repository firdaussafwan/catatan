<?php

namespace App\Support;

use App\Mail\RegistrationCode;
use App\Models\PendingRegistration;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Mail;

/** Self sign-up with an emailed 6-digit code (TAC). The admin can switch it off. */
final class Registration
{
    public const KEY = 'registration_open';
    public const TTL_MINUTES = 10;      // how long a code works
    public const MAX_ATTEMPTS = 5;      // wrong guesses before the code is burnt
    public const RESEND_SECONDS = 60;   // wait between two emails

    /**
     * Open when the admin has not switched it off AND the first (admin) account exists:
     * on a fresh install the person who opens /setup must become the admin, not a random visitor.
     */
    public static function isOpen(): bool
    {
        return Setting::bool(self::KEY, true) && User::query()->exists();
    }

    public static function newCode(): string
    {
        return str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
    }

    /** Only this hash is stored, so a leaked database does not leak valid codes. */
    public static function hash(string $code): string
    {
        return hash_hmac('sha256', trim($code), (string) config('app.key'));
    }

    /** Remember the sign-up (replacing an earlier unfinished one for the same email) and email a fresh code. */
    public static function start(string $name, string $email, string $password, ?string $ip): PendingRegistration
    {
        $code = self::newCode();

        $pending = PendingRegistration::updateOrCreate(['email' => $email], [
            'name' => $name,
            'password' => Crypt::encryptString($password),
            'code_hash' => self::hash($code),
            'attempts' => 0,
            'expires_at' => now()->addMinutes(self::TTL_MINUTES),
            'last_sent_at' => now(),
            'ip' => $ip,
        ]);

        Mail::to($email)->send(new RegistrationCode($code, $name));

        return $pending;
    }

    /** New code for an unfinished sign-up (the old code stops working). */
    public static function resend(PendingRegistration $pending): void
    {
        $code = self::newCode();

        $pending->update([
            'code_hash' => self::hash($code),
            'attempts' => 0,
            'expires_at' => now()->addMinutes(self::TTL_MINUTES),
            'last_sent_at' => now(),
        ]);

        Mail::to($pending->email)->send(new RegistrationCode($code, $pending->name));
    }
}
