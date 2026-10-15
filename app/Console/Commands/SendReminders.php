<?php

namespace App\Console\Commands;

use App\Mail\NoteReminder;
use App\Models\Note;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;
use Throwable;

class SendReminders extends Command
{
    protected $signature = 'catatan:send-reminders';

    protected $description = 'Email due reminders and 24-hour "due soon" warnings. Scheduled to run every minute.';

    public function handle(): int
    {
        $now = now();
        $sent = 0;

        // 1) Reminders whose time has come (also catches ones missed while the server was off).
        Note::with('user')->whereNull('parent_id')
            ->whereNotNull('remind_at')->whereNull('reminded_at')
            ->where('remind_at', '<=', $now)
            ->each(function (Note $note) use (&$sent) {
                $sent += $this->deliver($note, 'remind', 'reminded_at');
            });

        // 2) Notes due within the next 24 hours that we have not warned about yet.
        Note::with('user')->whereNull('parent_id')
            ->whereNotNull('due_at')->whereNull('due_notified_at')
            ->where('due_at', '>', $now)
            ->where('due_at', '<=', $now->copy()->addDay())
            ->each(function (Note $note) use (&$sent) {
                $sent += $this->deliver($note, 'due', 'due_notified_at');
            });

        $this->info("Sent {$sent} email(s).");

        return self::SUCCESS;
    }

    private function deliver(Note $note, string $kind, string $sentColumn): int
    {
        try {
            Mail::to($note->user->email)->send(new NoteReminder($note, $kind));
        } catch (Throwable $e) {
            // Leave it unmarked: it is retried next minute. Check storage/logs/laravel.log.
            report($e);
            $this->error("Note #{$note->id}: {$e->getMessage()}");

            return 0;
        }

        $note->timestamps = false; // sending an email is not an "edit"
        $note->forceFill([$sentColumn => now()])->save();

        return 1;
    }
}
