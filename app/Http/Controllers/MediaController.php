<?php

namespace App\Http\Controllers;

use App\Models\Attachment;
use App\Models\User;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Serves private files. Access is enforced by the route bindings (AppServiceProvider):
 * an attachment is only found if its note is visible to the signed-in user.
 *
 * response()->file() / ->download() support HTTP Range requests, which is what lets
 * videos seek and play on Android/iOS without downloading the whole file first.
 */
class MediaController extends Controller
{
    /** Images and videos play inline; anything else is sent as a download. */
    public function show(Attachment $attachment): BinaryFileResponse
    {
        $path = $this->path($attachment);
        // Rows created before attachments existed have no mime saved: sniff it from the file.
        $mime = $attachment->mime ?: (@mime_content_type($path) ?: 'application/octet-stream');
        $inline = array_merge(config('catatan.image_mimes'), config('catatan.video_mimes'));

        if (! in_array($mime, $inline, true)) {
            return $this->download($attachment);
        }

        return response()->file($path, [
            'Content-Type' => $mime,
            'Cache-Control' => 'private, max-age=604800',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    public function download(Attachment $attachment): BinaryFileResponse
    {
        // Always a generic type + "attachment": a downloaded file can never run as part of this site.
        return response()->download($this->path($attachment), $attachment->displayName(), [
            'Content-Type' => 'application/octet-stream',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    /** Profile picture of any user (names and pictures are visible wherever people post together). */
    public function avatar(User $user): BinaryFileResponse
    {
        abort_unless($user->avatar_path && Storage::disk('local')->exists($user->avatar_path), 404);

        return response()->file(Storage::disk('local')->path($user->avatar_path), [
            'Content-Type' => 'image/jpeg',
            'Cache-Control' => 'private, max-age=604800',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    private function path(Attachment $attachment): string
    {
        abort_unless(Storage::disk('local')->exists($attachment->path), 404);

        return Storage::disk('local')->path($attachment->path);
    }
}
