<!DOCTYPE html>
<html lang="en">
<body style="margin:0;padding:24px;background:#f4f7f8;font-family:system-ui,-apple-system,'Segoe UI',Roboto,Arial,sans-serif;color:#10181d">
    <div style="max-width:520px;margin:0 auto;background:#fff;border:1px solid #e3e9ec;border-radius:16px;padding:24px">
        <p style="margin:0 0 4px;font-size:13px;color:#0d9488;font-weight:700;letter-spacing:.04em;text-transform:uppercase">
            {{ $kind === 'due' ? 'Due soon' : 'Reminder' }}
        </p>

        @if ($kind === 'due')
            @php
                $secs = max(0, $note->due_at->getTimestamp() - now()->getTimestamp());
                $h = intdiv($secs, 3600);
                $m = intdiv($secs % 3600, 60);
            @endphp
            <h1 style="margin:0 0 6px;font-size:20px">Due in {{ $h }}h {{ $m }}m</h1>
            <p style="margin:0 0 16px;color:#5d6b73">{{ $note->due_at->format('l, j F Y \a\t H:i') }}</p>
        @else
            <h1 style="margin:0 0 16px;font-size:20px">You asked to be reminded</h1>
        @endif

        <div style="padding:14px 16px;background:#f4f7f8;border-radius:12px;white-space:pre-wrap;overflow-wrap:anywhere">{{ \Illuminate\Support\Str::limit($note->body !== '' ? $note->body : '(image note)', 800) }}</div>

        <p style="margin:20px 0 0">
            <a href="{{ $url }}" style="display:inline-block;padding:12px 20px;background:#0d9488;color:#fff;text-decoration:none;border-radius:999px;font-weight:600">Open note</a>
        </p>
        <p style="margin:20px 0 0;font-size:12px;color:#8b9aa3">Sent by {{ config('app.name') }}</p>
    </div>
</body>
</html>
