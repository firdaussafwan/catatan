<?php

return [
    // /setup is available only while there are no users AND this is true.
    'allow_setup' => (bool) env('ALLOW_SETUP', true),

    // Feeds
    'per_page' => 15,
    'max_body' => 10000,

    // Attachments (images, videos, any file)
    'max_attachments' => 6,
    'max_attachment_kb' => 102400, // 100 MB each. PHP's upload_max_filesize / post_max_size must allow it too.

    // Decided from the file's real content (not its extension). Anything else is a plain downloadable file.
    'image_mimes' => ['image/jpeg', 'image/png', 'image/gif', 'image/webp', 'image/avif'],
    'video_mimes' => ['video/mp4', 'video/webm', 'video/quicktime', 'video/ogg'],

    // Refused outright (programs/scripts) so the workspace can't be used to pass them around.
    'blocked_extensions' => [
        'exe', 'msi', 'bat', 'cmd', 'com', 'scr', 'pif', 'vbs', 'vbe', 'js', 'jse', 'wsf', 'ps1',
        'sh', 'bash', 'jar', 'apk', 'dll', 'php', 'phtml', 'phar', 'cgi', 'pl', 'htaccess',
    ],
];
