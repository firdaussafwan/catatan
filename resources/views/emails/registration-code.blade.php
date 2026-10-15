<!DOCTYPE html>
<html lang="en">
<body style="margin:0;padding:24px;background:#f4f7f8;font-family:system-ui,-apple-system,'Segoe UI',Roboto,Arial,sans-serif;color:#10181d">
    <div style="max-width:480px;margin:0 auto;background:#fff;border:1px solid #e3e9ec;border-radius:16px;padding:24px">
        <p style="margin:0 0 4px;font-size:13px;color:#0d9488;font-weight:700;letter-spacing:.04em;text-transform:uppercase">Confirm your email</p>
        <h1 style="margin:0 0 12px;font-size:20px">Hi {{ $name }}, here is your code</h1>
        <p style="margin:0 0 8px;color:#5d6b73">Enter this code to finish creating your {{ config('app.name') }} account:</p>
        <p style="margin:16px 0;padding:16px;background:#f4f7f8;border-radius:12px;text-align:center;font-size:32px;font-weight:700;letter-spacing:.35em">{{ $code }}</p>
        <p style="margin:0;color:#5d6b73;font-size:14px">The code works for {{ $minutes }} minutes. If you did not try to register, ignore this email: nothing was created.</p>
    </div>
</body>
</html>
