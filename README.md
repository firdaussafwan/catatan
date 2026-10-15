# Catatan — private notes, thread style

A private "X/Twitter for notes" that also works as a small team space: **My notes** (private), a **Public** feed,
and invite-only **Workspaces**. Post text with photos, videos and files, reply to build threads, star/pin, `#tags`,
search, filter by year / month / day, email reminders and due dates. Mobile-first (installable on Android), dark mode,
login with email + password, password reset by email (SMTP). No open sign-up: the admin creates the accounts.

**Stack:** Laravel 13 · Blade · SQLite · plain CSS/JS (no npm, no build step) · zero extra Composer packages.

## Why these choices

| Decision | Why |
|---|---|
| **Laravel + Blade** | Conventional MVC: every feature is `route → controller → view`, easy to find and change. Auth, sessions, mail, validation, rate-limiting, migrations and tests are built in, and Laravel has long-term support/upgrade paths. |
| **SQLite, not a JSON file** | Search, date filters and star/pin need queries; JSON would mean rewriting the whole file on every save (and risks corruption on concurrent writes). SQLite is still **one file** (`database/database.sqlite`) — back up by copying it. Moving to MySQL/Postgres later = change `DB_CONNECTION` in `.env`. |
| **Files on a private disk** | Stored in `storage/app/private`, served only through a signed-in route that checks you may read that note. Not guessable/public. |
| **No JS framework / no build** | `public/js/app.js` is ~150 lines of vanilla JS. Every feature still works as a plain HTML form if JS fails. |

## Install

Requires PHP 8.3+ (with `pdo_sqlite`, `gd`, `mbstring`, `fileinfo`) and Composer.

```bash
composer setup                 # install, copy .env, generate key, create sqlite file, migrate
php artisan serve              # http://localhost:8000
```

Open the site → **"First time? Create your account"** (the `/setup` page). It disappears once an account exists.
Afterwards set `ALLOW_SETUP=false` in `.env`.

Prefer the terminal (or locked out)? `php artisan catatan:user you@example.com` creates the account,
or sets a new password if it already exists.

## SMTP (forgot password)

Edit `.env`:

```env
MAIL_MAILER=smtp
MAIL_HOST=smtp.yourprovider.com
MAIL_PORT=587            # 587 -> MAIL_SCHEME=null (STARTTLS) | 465 -> MAIL_SCHEME=smtps
MAIL_SCHEME=null
MAIL_USERNAME=...
MAIL_PASSWORD=...
MAIL_FROM_ADDRESS="noreply@yourdomain.com"
APP_URL=https://your-domain        # the reset link in the email is built from this!
```

Then `php artisan config:clear`. While `MAIL_MAILER=log`, emails are only written to `storage/logs/laravel.log`.
Reset links expire after 60 minutes; requests are throttled.

## Who can do what

| | Normal user | Workspace owner | Admin |
|---|---|---|---|
| My notes (private, reminders, due dates) | yes | yes | yes |
| Read and post on **Public** (unless blocked / switched off / over the daily limit) | yes | yes | yes |
| Join a workspace (invite link, or request + approval) | yes | yes | sees all workspaces |
| Restrict / remove members, delete anyone's message, clear chat, make a new invite link | | **their** workspace | every workspace |
| Delete anyone's **public** post | | | yes |
| Create accounts, pause, suspend (temporary or until reactivated), delete, reset passwords, make admins | | | yes |
| Open / close public sign-up | | | yes |
| Create a workspace (and choose its owner) | | | yes |
| Kill switch + posts-per-day limit for Public | | | yes |

The **first account** (made on `/setup`) is the admin. Everyone else either signs up themselves (below) or gets an account
from **Admin → Create an account**, and can change the password in Settings. Admins can never read other people's *private* notes.

## Sign-up with a TAC (email code)

Anyone can register at `/register`: name, email, password, then a 6-digit code sent by email (valid 10 minutes, 5 tries,
resend every 60 s). The account is only created once the code is confirmed. Needs working SMTP and a correct `APP_URL`.
Sign-up is **on** once an admin exists (closed on a fresh install, so nobody can beat you to `/setup`).
Admin → **Sign-up** switches it on or off.

## Account controls (Admin → Accounts)

- **Pause**: the user can still sign in and read, but cannot post or reply. **Resume** undoes it.
- **Suspend**: temporary terminate for 1 / 7 / 30 days or until you reactivate. The user is signed out at once and cannot log in.
- **Delete**: permanently removes the account with all its notes and files. Not allowed for yourself or for someone who still owns a workspace.

After updating run `php artisan migrate` (adds the pause/suspend columns and the pending sign-up table).

## Public feed

Everyone with an account can read it and post to it. In **Admin → Public sharing**:

* **Kill switch**: untick "Public sharing is ON" and nobody can post or reply publicly. Reading still works.
* **Posts per user per day**: `0` = unlimited. Counts new public posts (replies don't count), reset at midnight `APP_TIMEZONE`.
* Per person: **Stop public posting** next to their name.

## Workspaces

1. An admin opens one on **Workspaces → Open a new workspace** and picks the **owner**.
2. The owner opens **Manage** and either
   * copies the **invite link** and shares it: anyone with an account who opens it joins straight away
     (a new link kills the old one; the link can be switched off), or
   * approves **join requests** from people who found it in the directory (untick "Show in the workspace directory" to hide it).
3. Moderation (owner/admin): **Restrict** a member (can read, can't post/reply), **Remove** a member (optionally also wiping their
   messages), **Remove** any single message, **Clear all messages**, or delete the whole workspace.
   A removed member can come back through a valid invite link, so make a new link after removing someone.

Only members (and admins) can see a workspace's messages and files; everyone else gets a 404.

## Attachments (photos, videos, files)

Attach with the paperclip on any note, public post, workspace message or reply (up to 6 per post, 100 MB each).

* **Images** show inline (tap to enlarge). **Videos** play inline like a reel: muted autoplay while on screen, tap for controls/sound,
  seek works on phones (HTTP Range). Autoplay is skipped when the phone has Data Saver on.
* **Any other file** (PDF, Word, zip...) shows as a card with a **Download** button.
* The type is decided from the file's real content, not its name. Programs and scripts (`.exe`, `.bat`, `.js`, `.php`, `.sh`...)
  are refused, see `blocked_extensions` in `config/catatan.php`. Downloads are always sent as a generic file, never run in the browser.

**Raise PHP's upload limits** or big files fail with "page expired"/413. In `php.ini` (Laragon: menu → PHP → php.ini):

```ini
upload_max_filesize = 110M
post_max_size = 120M
max_execution_time = 300
```
Restart Apache/PHP afterwards. Behind nginx also set `client_max_body_size 120M;`. Lower `max_attachment_kb` in `config/catatan.php` if you want smaller limits.

## Reminders & due dates

In the composer (and when editing a note) open **Reminder & due date**:

* **Email me a reminder at** a date/time, or tap a quick option (1 hour, 3 hours, tomorrow 9:00, 1 day, 1 week, 1 month).
* **Due date**: you get an email **24 hours before**, and the note shows a live countdown badge
  (green > 3 days, amber < 3 days, red < 24 h, solid red = overdue). If you set a due date less than 24 h away,
  the warning is sent within a minute. "Due dates" in the sidebar lists notes soonest-first.
* Editing a time re-arms its email. Times use `APP_TIMEZONE` (default `Asia/Kuala_Lumpur`).

Emails are sent by `php artisan catatan:send-reminders`, which the scheduler runs **every minute**. The scheduler must be running:

```bash
php artisan schedule:work          # local/Laragon: keep this terminal open
php artisan catatan:send-reminders # run once by hand to test (needs SMTP set up, see above)
```
Production: Linux cron `* * * * * cd /path/to/catatan && php artisan schedule:run >> /dev/null 2>&1`,
or Windows Task Scheduler every minute: `C:\laragon\bin\php\php-8.x\php.exe C:\laragon\www\catatan\artisan schedule:run`.
Reminders missed while the server was off are sent when it comes back. If SMTP fails, the email is retried each minute
(see `storage/logs/laravel.log`). Run `php artisan migrate` after updating.

## Staying signed in

* Session lasts 30 days (`SESSION_LIFETIME=43200`), sliding.
* "Keep me signed in" (ticked by default) adds a long-lived remember cookie.
* Change a password (Settings or reset) → old remember cookies are invalidated.

## Deploying

```bash
APP_ENV=production  APP_DEBUG=false  SESSION_SECURE_COOKIE=true   # in .env, serve over HTTPS
composer install --no-dev -o && php artisan migrate --force && php artisan config:cache route:cache view:cache
```
Point the web root at `public/`. `storage/` and `bootstrap/cache/` must be writable.
**Backup = copy `database/database.sqlite` + `storage/app/private/`.**

## Where to change things

| I want to… | Edit |
|---|---|
| Add a route | `routes/web.php` |
| Feed filters / queries | `NoteController@index`, scopes in `app/Models/Note.php` |
| Who can read a note | `Note::scopeVisibleTo()` + route bindings in `AppServiceProvider` |
| Public limit / kill switch rules | `app/Support/Sharing.php`, settings stored in the `app_settings` table |
| Workspace rules (join, restrict, kick) | `app/Models/Workspace.php`, `WorkspaceController` |
| Upload rules (types, limits, saving) | `app/Support/NoteWriter.php`, `config/catatan.php`, `MediaController` |
| Hashtag / link rendering | `Note::renderBody()`, `Note::extractTags()` |
| Limits (images, size, page size) | `config/catatan.php` |
| Look & feel | `public/css/app.css` (colour variables at the top) |
| Cards / pages | `resources/views/notes/*`, filters in `resources/views/partials/rail.blade.php` |
| Front-end behaviour | `public/js/app.js` |
| Password reset / login logic | `app/Http/Controllers/AuthController.php` |

## Tests

```bash
php artisan test
```
`tests/Feature/CatatanTest.php` covers login, setup lock, password-reset mail, tags, search, date filters, star, threads,
ownership isolation, XSS escaping, reminders and due dates, private/inline/download attachments, the public feed, admin limit /
kill switch / block / suspend, and workspaces (invite link, requests, restrict, kick, moderation, clear, delete).

## Security notes

CSRF on every form · bcrypt passwords · login throttled (5/min per email+IP) · reset flow doesn't reveal which
emails exist · every read is limited by `visibleTo()` (private = owner only, workspace = members only) and anything
you may not see is a plain 404 · uploads stored under random names on a private disk and sniffed from content, non-media
files are always forced to download, programs/scripts refused, everything served with `nosniff` · admin area returns 404
to non-admins · suspended accounts are signed out on their next request · body text is HTML-escaped before links/#tags are added.
