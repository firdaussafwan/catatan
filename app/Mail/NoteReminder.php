<?php

namespace App\Mail;

use App\Models\Note;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Support\Str;

class NoteReminder extends Mailable
{
    /** @param  'remind'|'due'  $kind */
    public function __construct(public Note $note, public string $kind)
    {
    }

    public function envelope(): Envelope
    {
        $snippet = Str::limit(trim(preg_replace('/\s+/', ' ', $this->note->body)) ?: '(image note)', 60);

        return new Envelope(
            subject: ($this->kind === 'due' ? 'Due soon: ' : 'Reminder: ').$snippet,
        );
    }

    public function content(): Content
    {
        return new Content(view: 'emails.reminder', with: [
            'note' => $this->note,
            'kind' => $this->kind,
            'url' => route('notes.show', $this->note),
        ]);
    }
}
