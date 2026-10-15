<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** A file on a note: image and video are shown inline, anything else is a download card. */
class Attachment extends Model
{
    protected $fillable = ['note_id', 'path', 'sort', 'kind', 'original_name', 'mime', 'size'];

    public function note(): BelongsTo
    {
        return $this->belongsTo(Note::class);
    }

    public function isImage(): bool
    {
        return $this->kind === 'image';
    }

    public function isVideo(): bool
    {
        return $this->kind === 'video';
    }

    /** Inline URL (images/videos play from here). Authenticated: the files are on the private disk. */
    public function url(): string
    {
        return route('attachments.show', $this);
    }

    public function downloadUrl(): string
    {
        return route('attachments.download', $this);
    }

    public function displayName(): string
    {
        return $this->original_name ?: basename($this->path);
    }

    public function humanSize(): string
    {
        $bytes = (float) $this->size;
        foreach (['B', 'KB', 'MB', 'GB'] as $i => $unit) {
            if ($bytes < 1024 || $unit === 'GB') {
                return ($i === 0 ? (string) (int) $bytes : number_format($bytes, 1)).' '.$unit;
            }
            $bytes /= 1024;
        }

        return '';
    }
}
