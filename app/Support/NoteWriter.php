<?php

namespace App\Support;

use App\Models\Attachment;
use App\Models\Note;
use App\Models\User;
use Closure;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/** Shared by every place that creates a note (private, public, workspace, replies): validation + saving + files. */
final class NoteWriter
{
    /** Validation for the text + attachments of a note. */
    public static function rules(): array
    {
        return [
            'body' => ['nullable', 'string', 'max:'.config('catatan.max_body'), 'required_without:files'],
            'files' => ['nullable', 'array', 'max:'.config('catatan.max_attachments')],
            'files.*' => ['file', 'max:'.config('catatan.max_attachment_kb'), self::notBlockedType()],
        ];
    }

    private static function notBlockedType(): Closure
    {
        return function (string $attribute, mixed $file, Closure $fail) {
            if ($file instanceof UploadedFile
                && in_array(strtolower($file->getClientOriginalExtension()), config('catatan.blocked_extensions'), true)) {
                $fail('Programs and scripts (.'.strtolower($file->getClientOriginalExtension()).') can not be uploaded.');
            }
        };
    }

    /**
     * Create a note (or a reply) with its files in one transaction.
     *
     * @param  UploadedFile[]  $files
     * @param  array  $attrs  parent_id, visibility, workspace_id, remind_at, due_at
     */
    public static function create(User $user, string $body, array $files, array $attrs = []): Note
    {
        return DB::transaction(function () use ($user, $body, $files, $attrs) {
            $note = Note::create(['user_id' => $user->id, 'body' => $body] + $attrs);

            $note->syncTags();
            self::storeFiles($note, $files);

            return $note;
        });
    }

    /** @param  UploadedFile[]  $files */
    public static function storeFiles(Note $note, array $files, int $startSort = 0): void
    {
        foreach (array_values($files) as $i => $file) {
            $mime = $file->getMimeType() ?: 'application/octet-stream'; // sniffed from the content, not the extension

            $note->attachments()->create([
                'path' => $file->storeAs('notes/'.$note->user_id, Str::random(40), 'local'),
                'sort' => $startSort + $i,
                'kind' => self::kindOf($mime),
                'original_name' => Str::limit(preg_replace('/[\x00-\x1f\/\\\\]/', '', $file->getClientOriginalName()) ?: 'file', 150, ''),
                'mime' => $mime,
                'size' => $file->getSize() ?: 0,
            ]);
        }
    }

    public static function kindOf(string $mime): string
    {
        return match (true) {
            in_array($mime, config('catatan.image_mimes'), true) => 'image',
            in_array($mime, config('catatan.video_mimes'), true) => 'video',
            default => 'file',
        };
    }
}
