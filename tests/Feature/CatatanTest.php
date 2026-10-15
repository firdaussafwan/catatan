<?php

namespace Tests\Feature;

use App\Mail\NoteReminder;
use App\Mail\RegistrationCode;
use App\Models\Note;
use App\Models\Setting;
use App\Models\Workspace;
use App\Models\WorkspaceMember;
use App\Models\WorkspaceRequest;
use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use App\Support\Registration;
use Tests\TestCase;

class CatatanTest extends TestCase
{
    use RefreshDatabase;

    private function user(array $attrs = []): User
    {
        return User::create(array_merge([
            'name' => 'Safwan',
            'email' => 'me@example.com',
            'password' => 'secret-pass-1',
        ], $attrs));
    }

    private function note(User $user, string $body, ?string $at = null): Note
    {
        $note = $user->notes()->create(['body' => $body]);
        if ($at) {
            $note->forceFill(['created_at' => $at, 'updated_at' => $at])->save();
        }
        $note->syncTags();

        return $note;
    }

    /* ----------------------------------------------------------------- access */

    public function test_guests_are_sent_to_the_login_front_page(): void
    {
        $this->get('/home')->assertRedirect(route('login'));
        $this->get('/')->assertOk()->assertSee('Sign in');
    }

    public function test_login_works_and_wrong_password_is_rejected(): void
    {
        $this->user();

        $this->post('/login', ['email' => 'me@example.com', 'password' => 'nope'])
            ->assertSessionHasErrors('email');
        $this->assertGuest();

        $this->post('/login', ['email' => 'me@example.com', 'password' => 'secret-pass-1', 'remember' => 1])
            ->assertRedirect(route('notes.index'));
        $this->assertAuthenticated();
    }

    public function test_setup_is_open_only_until_the_first_user_exists(): void
    {
        $this->get('/setup')->assertOk();

        $this->post('/setup', [
            'name' => 'Safwan', 'email' => 'me@example.com',
            'password' => 'secret-pass-1', 'password_confirmation' => 'secret-pass-1',
        ])->assertRedirect(route('notes.index'));

        auth()->logout();
        $this->get('/setup')->assertNotFound();
    }

    public function test_forgot_password_sends_a_reset_email(): void
    {
        Notification::fake();
        $user = $this->user();

        $this->post('/forgot-password', ['email' => $user->email])->assertSessionHas('status');
        Notification::assertSentTo($user, ResetPassword::class);
    }

    /* ------------------------------------------------------------------ notes */

    public function test_create_note_with_hashtags_and_filter_by_tag_and_search(): void
    {
        $user = $this->user();

        $this->actingAs($user)->post('/notes', ['body' => 'Plan the #Laravel upgrade'])->assertRedirect(route('notes.index'));
        $this->note($user, 'Buy milk #home');

        $this->actingAs($user)->get('/home?tag=laravel')
            ->assertOk()->assertSee('Plan the')->assertDontSee('Buy milk');

        $this->actingAs($user)->get('/home?q=milk')
            ->assertOk()->assertSee('Buy milk')->assertDontSee('Plan the');
    }

    public function test_search_treats_percent_literally(): void
    {
        $user = $this->user();
        $this->note($user, 'discount 50% today');
        $this->note($user, 'nothing special');

        $this->actingAs($user)->get('/home?q=50%25')->assertSee('discount')->assertDontSee('nothing special');
    }

    public function test_filter_by_year_month_day(): void
    {
        $user = $this->user();
        $this->note($user, 'old march note', '2024-03-05 10:00:00');
        $this->note($user, 'fresh october note', now()->toDateTimeString());

        $this->actingAs($user)->get('/home?year=2024&month=3&day=5')
            ->assertSee('old march note')->assertDontSee('fresh october note');

        $this->actingAs($user)->get('/home?year=2024&month=4')
            ->assertDontSee('old march note');

        $this->actingAs($user)->get('/home?year=2024')
            ->assertSee('old march note')->assertDontSee('fresh october note');
    }

    public function test_star_toggles_via_json_and_starred_filter_works(): void
    {
        $user = $this->user();
        $starred = $this->note($user, 'important thing');
        $this->note($user, 'ordinary thing');

        $this->actingAs($user)->postJson(route('notes.star', $starred))->assertJson(['on' => true]);
        $this->assertTrue($starred->fresh()->is_starred);
        $this->assertFalse($starred->fresh()->wasEdited(), 'starring must not mark a note as edited');

        $this->actingAs($user)->get('/home?starred=1')
            ->assertSee('important thing')->assertDontSee('ordinary thing');

        $this->actingAs($user)->postJson(route('notes.star', $starred))->assertJson(['on' => false]);
    }

    public function test_replies_form_a_thread(): void
    {
        $user = $this->user();
        $root = $this->note($user, 'root note');

        $this->actingAs($user)->post(route('notes.reply', $root), ['body' => 'a reply'])->assertRedirect();
        $this->assertSame($root->id, Note::where('body', 'a reply')->firstOrFail()->parent_id);

        $this->actingAs($user)->get(route('notes.show', $root))->assertSee('a reply');
        // replies are not listed as separate feed items, but search finds the root through them
        $this->actingAs($user)->get('/home?q=reply')->assertSee('root note');
    }

    public function test_users_cannot_touch_each_others_notes(): void
    {
        $owner = $this->user();
        $other = $this->user(['email' => 'other@example.com']);
        $note = $this->note($owner, 'private');

        $this->actingAs($other)->get(route('notes.show', $note))->assertNotFound();
        $this->actingAs($other)->delete(route('notes.destroy', $note))->assertNotFound();
        $this->assertDatabaseHas('notes', ['id' => $note->id]);
    }

    public function test_body_is_escaped(): void
    {
        $user = $this->user();
        $this->note($user, '<script>alert(1)</script> https://example.com/a#frag');

        $this->actingAs($user)->get('/home')
            ->assertDontSee('<script>alert(1)</script>', false)
            ->assertSee('&lt;script&gt;', false)
            ->assertSee('href="https://example.com/a#frag"', false);
    }

    /* ------------------------------------------------------------ attachments */

    public function test_files_are_classified_stored_privately_and_deleted_with_the_note(): void
    {
        Storage::fake('local');
        $owner = $this->user();
        $other = $this->user(['email' => 'other@example.com']);

        $this->actingAs($owner)->post('/notes', [
            'body' => 'with stuff',
            'files' => [
                UploadedFile::fake()->image('pic.jpg'),
                UploadedFile::fake()->create('clip.mp4', 500, 'video/mp4'),
                UploadedFile::fake()->create('report.pdf', 100, 'application/pdf'),
            ],
        ])->assertSessionHasNoErrors();

        $note = Note::firstOrFail();
        $this->assertSame(['file', 'image', 'video'], $note->attachments->pluck('kind')->sort()->values()->all());

        $image = $note->attachments->firstWhere('kind', 'image');
        $video = $note->attachments->firstWhere('kind', 'video');
        $file = $note->attachments->firstWhere('kind', 'file');
        Storage::disk('local')->assertExists($image->path);

        // images + videos play inline, anything else is forced to download as a generic type
        $this->actingAs($owner)->get($image->url())->assertOk()->assertHeader('Content-Type', $image->mime);
        $this->assertStringNotContainsString('attachment', (string) $this->actingAs($owner)->get($video->url())->headers->get('Content-Disposition'));
        $download = $this->actingAs($owner)->get($file->url())->assertOk()->assertHeader('Content-Type', 'application/octet-stream');
        $this->assertStringContainsString('attachment', (string) $download->headers->get('Content-Disposition'));
        $this->actingAs($owner)->get($file->downloadUrl())->assertOk();

        // videos can seek (HTTP Range) which is what makes them play on phones
        $this->actingAs($owner)->withHeaders(['Range' => 'bytes=0-9'])->get($video->url())->assertStatus(206);

        // private: other people and guests cannot fetch them
        $this->actingAs($other)->get($image->url())->assertNotFound();
        auth()->logout();
        $this->get($image->url())->assertRedirect(route('login'));

        $this->actingAs($owner)->delete(route('notes.destroy', $note));
        Storage::disk('local')->assertMissing($image->path);
        $this->assertDatabaseCount('attachments', 0);
    }

    public function test_programs_are_refused_and_the_attachment_count_is_limited(): void
    {
        Storage::fake('local');
        $user = $this->user();

        $this->actingAs($user)->post('/notes', ['body' => 'x', 'files' => [UploadedFile::fake()->create('evil.php', 1)]])
            ->assertSessionHasErrors('files.0');

        $this->actingAs($user)->post('/notes', [
            'body' => 'too many',
            'files' => array_map(fn ($i) => UploadedFile::fake()->image("$i.jpg"), range(1, 7)),
        ])->assertSessionHasErrors('files');
    }

    public function test_profile_picture_can_be_uploaded_served_and_removed(): void
    {
        Storage::fake('local');
        $user = $this->user();

        $this->actingAs($user)->post(route('settings.avatar'), [
            'avatar' => UploadedFile::fake()->image('me.png', 600, 400),
        ])->assertSessionHasNoErrors();

        $path = $user->fresh()->avatar_path;
        $this->assertNotNull($path);
        Storage::disk('local')->assertExists($path);
        $this->actingAs($user)->get(route('avatar.show', $user))->assertOk();
        $this->actingAs($user)->get('/home')->assertSee(route('avatar.show', ['user' => $user->id, 'v' => substr(md5($path), 0, 8)]), false);

        $this->actingAs($user)->delete(route('settings.avatar.remove'));
        Storage::disk('local')->assertMissing($path);
        $this->assertNull($user->fresh()->avatar_path);
    }

    /* ------------------------------------------------- reminders & due dates */

    public function test_note_can_be_created_with_reminder_and_due_date_and_past_reminder_is_rejected(): void
    {
        $user = $this->user();
        $remind = now()->addHour()->format('Y-m-d\TH:i');
        $due = now()->addDays(2)->format('Y-m-d\TH:i');

        $this->actingAs($user)->post('/notes', ['body' => 'with dates', 'remind_at' => $remind, 'due_at' => $due])
            ->assertSessionHasNoErrors();

        $note = Note::firstOrFail();
        $this->assertSame($remind, $note->remind_at->format('Y-m-d\TH:i'));
        $this->assertSame($due, $note->due_at->format('Y-m-d\TH:i'));
        $this->actingAs($user)->get('/home')->assertSee('Due in');

        $this->actingAs($user)->post('/notes', ['body' => 'too late', 'remind_at' => now()->subHour()->format('Y-m-d\TH:i')])
            ->assertSessionHasErrors('remind_at');
    }

    public function test_reminder_email_is_sent_once_at_the_chosen_time(): void
    {
        Mail::fake();
        $user = $this->user();
        $ready = $this->note($user, 'call the bank');
        $ready->forceFill(['remind_at' => now()->subMinute()])->save();
        $later = $this->note($user, 'not yet');
        $later->forceFill(['remind_at' => now()->addHour()])->save();

        $this->artisan('catatan:send-reminders')->assertSuccessful();

        Mail::assertSent(NoteReminder::class, 1);
        Mail::assertSent(NoteReminder::class, fn ($m) => $m->kind === 'remind' && $m->note->is($ready) && $m->hasTo('me@example.com'));
        $this->assertNotNull($ready->fresh()->reminded_at);
        $this->assertNull($later->fresh()->reminded_at);

        $this->artisan('catatan:send-reminders'); // nothing new to send
        Mail::assertSent(NoteReminder::class, 1);
    }

    public function test_due_warning_goes_out_24_hours_before_and_is_rearmed_when_the_date_moves(): void
    {
        Mail::fake();
        $user = $this->user();
        $soon = $this->note($user, 'tomorrow task');
        $soon->forceFill(['due_at' => now()->addHours(20)])->save();
        $far = $this->note($user, 'next week task');
        $far->forceFill(['due_at' => now()->addDays(3)])->save();

        $this->artisan('catatan:send-reminders')->assertSuccessful();

        Mail::assertSent(NoteReminder::class, fn ($m) => $m->kind === 'due' && $m->note->is($soon));
        Mail::assertNotSent(NoteReminder::class, fn ($m) => $m->note->is($far));
        $this->assertNotNull($soon->fresh()->due_notified_at);

        $this->actingAs($user)->put(route('notes.update', $soon), [
            'body' => 'tomorrow task',
            'due_at' => now()->addHours(22)->format('Y-m-d\TH:i'),
        ])->assertSessionHasNoErrors();
        $this->assertNull($soon->fresh()->due_notified_at);
    }

    public function test_countdown_states(): void
    {
        $note = new Note();

        $note->due_at = now()->addHours(5)->addMinutes(30)->addSeconds(5);
        $this->assertSame(['text' => 'Due in 5h 30m', 'state' => 'soon'], $note->countdown());

        $note->due_at = now()->addDays(5);
        $this->assertSame('ok', $note->countdown()['state']);

        $note->due_at = now()->subHours(2);
        $this->assertSame('over', $note->countdown()['state']);
    }

    /* ------------------------------------------------ public feed + admin */

    private function admin(): User
    {
        $admin = $this->user(['email' => 'admin@example.com', 'name' => 'Admin']);
        $admin->forceFill(['is_admin' => true])->save();

        return $admin;
    }

    public function test_public_feed_is_shared_and_private_notes_stay_private(): void
    {
        $a = $this->user();
        $b = $this->user(['email' => 'b@example.com', 'name' => 'Bee']);
        $private = $this->note($a, 'my private diary');

        $this->actingAs($a)->post(route('public.store'), ['body' => 'hello everyone'])->assertRedirect(route('public.index'));
        $root = Note::where('body', 'hello everyone')->firstOrFail();

        $this->actingAs($b)->get(route('public.index'))->assertSee('hello everyone')->assertDontSee('my private diary');
        $this->actingAs($b)->get('/home')->assertDontSee('hello everyone');   // public posts are not in My notes
        $this->actingAs($b)->get(route('notes.show', $private))->assertNotFound();

        $this->actingAs($b)->post(route('notes.reply', $root), ['body' => 'nice one'])->assertSessionHasNoErrors();
        $this->actingAs($a)->get(route('notes.show', $root))->assertSee('nice one');

        // only the author edits; a stranger cannot delete
        $this->actingAs($b)->get(route('notes.edit', $root))->assertNotFound();
        $this->actingAs($b)->delete(route('notes.moderate', $root))->assertForbidden();
    }

    public function test_hashtags_in_shared_posts_are_plain_text_not_links_into_my_notes(): void
    {
        $a = $this->user();
        $this->actingAs($a)->post(route('public.store'), ['body' => 'party #friday']);

        $this->actingAs($a)->get(route('public.index'))
            ->assertSee('#friday')
            ->assertDontSee('href="'.route('notes.index', ['tag' => 'friday']).'"', false);
    }

    public function test_admin_daily_limit_kill_switch_and_per_user_block(): void
    {
        $admin = $this->admin();
        $u = $this->user();

        $this->actingAs($admin)->put(route('admin.settings'), ['public_enabled' => 1, 'daily_limit' => 2])->assertSessionHasNoErrors();
        $this->actingAs($u)->post(route('public.store'), ['body' => 'one'])->assertSessionHasNoErrors();
        $this->actingAs($u)->post(route('public.store'), ['body' => 'two'])->assertSessionHasNoErrors();
        $this->actingAs($u)->post(route('public.store'), ['body' => 'three'])->assertSessionHasErrors('body');
        $this->assertSame(2, Note::where('visibility', 'public')->count());

        // kill switch (checkbox left unticked = OFF) stops everybody, limit or not
        $this->actingAs($admin)->put(route('admin.settings'), ['daily_limit' => 0]);
        $this->actingAs($u)->post(route('public.store'), ['body' => 'four'])->assertSessionHasErrors('body');
        $this->actingAs($u)->get(route('public.index'))->assertOk()->assertSee('switched off'); // reading still works

        // back on, but this one user is blocked
        $this->actingAs($admin)->put(route('admin.settings'), ['public_enabled' => 1, 'daily_limit' => 0]);
        $this->actingAs($admin)->post(route('admin.users.block', $u));
        $this->actingAs($u)->post(route('public.store'), ['body' => 'five'])->assertSessionHasErrors('body');
    }

    public function test_admin_area_is_hidden_from_normal_users_and_admin_can_create_accounts(): void
    {
        $admin = $this->admin();
        $u = $this->user();

        $this->actingAs($u)->get(route('admin.index'))->assertNotFound();
        $this->actingAs($u)->post(route('admin.users.store'), ['name' => 'X', 'email' => 'x@example.com', 'password' => 'abcdefgh'])->assertNotFound();

        $this->actingAs($admin)->get(route('admin.index'))->assertOk();
        $this->actingAs($admin)->post(route('admin.users.store'), ['name' => 'New', 'email' => 'new@example.com', 'password' => 'abcdefgh'])
            ->assertSessionHasNoErrors();
        $this->assertDatabaseHas('users', ['email' => 'new@example.com', 'is_admin' => false]);
    }

    public function test_suspended_account_is_signed_out(): void
    {
        $admin = $this->admin();
        $u = $this->user();

        $this->actingAs($admin)->post(route('admin.users.suspend', $u));
        $this->actingAs($u->fresh())->get('/home')->assertRedirect(route('login'));
    }

    /* ----------------------------------------------------------- workspaces */

    private function workspace(User $owner, string $name = 'Team'): Workspace
    {
        $w = Workspace::create(['name' => $name, 'owner_id' => $owner->id]);
        WorkspaceMember::create(['workspace_id' => $w->id, 'user_id' => $owner->id, 'role' => 'owner']);

        return $w;
    }

    private function join(Workspace $w, User $user): WorkspaceMember
    {
        return WorkspaceMember::create(['workspace_id' => $w->id, 'user_id' => $user->id, 'role' => 'member']);
    }

    public function test_only_an_admin_can_open_a_workspace(): void
    {
        $admin = $this->admin();
        $u = $this->user();
        $data = ['name' => 'Dev team', 'owner_id' => $u->id];

        $this->actingAs($u)->post(route('workspaces.store'), $data)->assertForbidden();
        $this->actingAs($admin)->post(route('workspaces.store'), $data)->assertSessionHasNoErrors();

        $w = Workspace::firstOrFail();
        $this->assertTrue($w->membershipOf($u)->isOwner());
    }

    public function test_invite_link_lets_people_in_and_outsiders_see_nothing(): void
    {
        $owner = $this->user();
        $member = $this->user(['email' => 'm@example.com', 'name' => 'Mem']);
        $outsider = $this->user(['email' => 'o@example.com', 'name' => 'Out']);
        $w = $this->workspace($owner);

        $this->actingAs($member)->get(route('workspaces.join', $w->join_token))->assertOk()->assertSee('Join workspace');
        $this->actingAs($member)->post(route('workspaces.join.store', $w->join_token))->assertRedirect(route('workspaces.show', $w));
        $this->actingAs($member)->post(route('workspaces.post', $w), ['body' => 'team secret'])->assertSessionHasNoErrors();

        $this->actingAs($owner)->get(route('workspaces.show', $w))->assertSee('team secret');

        $post = Note::where('body', 'team secret')->firstOrFail();
        $this->actingAs($outsider)->get(route('workspaces.show', $w))->assertNotFound();
        $this->actingAs($outsider)->get(route('notes.show', $post))->assertNotFound();
        $this->actingAs($outsider)->post(route('workspaces.post', $w), ['body' => 'let me in'])->assertNotFound();
        $this->actingAs($outsider)->get(route('public.index'))->assertDontSee('team secret');
        $this->actingAs($outsider)->get(route('workspaces.index'))->assertDontSee('team secret');
    }

    public function test_a_new_invite_link_kills_the_old_one_and_it_can_be_switched_off(): void
    {
        $owner = $this->user();
        $u = $this->user(['email' => 'u@example.com']);
        $w = $this->workspace($owner);
        $old = $w->join_token;

        $this->actingAs($u)->post(route('workspaces.link', $w))->assertNotFound(); // not a manager
        $this->actingAs($owner)->post(route('workspaces.link', $w));
        $this->actingAs($u)->get(route('workspaces.join', $old))->assertNotFound();

        $w->refresh();
        $this->actingAs($owner)->put(route('workspaces.update', $w), ['name' => 'Team', 'is_listed' => 1]); // invite_enabled unticked
        $this->actingAs($u)->get(route('workspaces.join', $w->join_token))->assertNotFound();
    }

    public function test_request_to_join_needs_the_owners_approval(): void
    {
        $owner = $this->user();
        $u = $this->user(['email' => 'u@example.com']);
        $w = $this->workspace($owner);

        $this->actingAs($u)->get(route('workspaces.index'))->assertSee('Request to join');
        $this->actingAs($u)->post(route('workspaces.request', $w))->assertSessionHasNoErrors();
        $req = WorkspaceRequest::firstOrFail();

        $this->actingAs($u)->post(route('workspaces.requests.approve', [$w, $req]))->assertNotFound();
        $this->assertNull($w->membershipOf($u));

        $this->actingAs($owner)->post(route('workspaces.requests.approve', [$w, $req]))->assertSessionHasNoErrors();
        $this->assertNotNull($w->membershipOf($u));
        $this->assertDatabaseCount('workspace_requests', 0);
    }

    public function test_declined_request_does_not_get_in(): void
    {
        $owner = $this->user();
        $u = $this->user(['email' => 'u@example.com']);
        $w = $this->workspace($owner);
        $this->actingAs($u)->post(route('workspaces.request', $w));

        $this->actingAs($owner)->delete(route('workspaces.requests.deny', [$w, WorkspaceRequest::firstOrFail()]));
        $this->assertNull($w->membershipOf($u));
        $this->assertDatabaseCount('workspace_requests', 0);
    }

    public function test_restricted_member_can_read_but_not_post_or_reply(): void
    {
        $owner = $this->user();
        $m = $this->user(['email' => 'm@example.com']);
        $w = $this->workspace($owner);
        $member = $this->join($w, $m);

        $this->actingAs($owner)->post(route('workspaces.post', $w), ['body' => 'announcement']);
        $root = Note::firstOrFail();

        $this->actingAs($owner)->post(route('workspaces.members.restrict', [$w, $member]))->assertSessionHasNoErrors();
        $this->assertTrue($member->fresh()->is_restricted);

        $this->actingAs($m)->get(route('workspaces.show', $w))->assertOk()->assertSee('announcement');
        $this->actingAs($m)->post(route('workspaces.post', $w), ['body' => 'hi'])->assertSessionHasErrors('body');
        $this->actingAs($m)->post(route('notes.reply', $root), ['body' => 'hi'])->assertSessionHasErrors('body');

        $this->actingAs($owner)->post(route('workspaces.members.restrict', [$w, $member])); // lift it
        $this->actingAs($m)->post(route('workspaces.post', $w), ['body' => 'thanks'])->assertSessionHasNoErrors();
    }

    public function test_only_the_owner_can_restrict_kick_or_delete_other_peoples_messages(): void
    {
        Storage::fake('local');
        $owner = $this->user();
        $a = $this->user(['email' => 'a@example.com', 'name' => 'Aa']);
        $b = $this->user(['email' => 'b@example.com', 'name' => 'Bb']);
        $w = $this->workspace($owner);
        $memberA = $this->join($w, $a);
        $this->join($w, $b);

        $this->actingAs($a)->post(route('workspaces.post', $w), [
            'body' => 'from a', 'files' => [UploadedFile::fake()->image('a.jpg')],
        ])->assertSessionHasNoErrors();
        $post = Note::where('body', 'from a')->firstOrFail();
        $path = $post->attachments()->firstOrFail()->path;

        // a plain member can do none of it
        $this->actingAs($b)->post(route('workspaces.members.restrict', [$w, $memberA]))->assertNotFound();
        $this->actingAs($b)->delete(route('workspaces.members.kick', [$w, $memberA]))->assertNotFound();
        $this->actingAs($b)->delete(route('notes.moderate', $post))->assertForbidden();

        // the owner deletes the message (and its file)
        $this->actingAs($owner)->delete(route('notes.moderate', $post))->assertRedirect();
        $this->assertDatabaseMissing('notes', ['id' => $post->id]);
        Storage::disk('local')->assertMissing($path);
    }

    public function test_kick_can_wipe_the_members_messages_and_locks_them_out(): void
    {
        $owner = $this->user();
        $a = $this->user(['email' => 'a@example.com']);
        $w = $this->workspace($owner);
        $memberA = $this->join($w, $a);

        $this->actingAs($a)->post(route('workspaces.post', $w), ['body' => 'spam spam']);
        $this->actingAs($owner)->post(route('workspaces.post', $w), ['body' => 'owner message']);

        $this->actingAs($owner)->delete(route('workspaces.members.kick', [$w, $memberA]), ['delete_messages' => 1])
            ->assertSessionHasNoErrors();

        $this->assertNull($w->membershipOf($a));
        $this->assertDatabaseMissing('notes', ['body' => 'spam spam']);
        $this->assertDatabaseHas('notes', ['body' => 'owner message']);
        $this->actingAs($a)->get(route('workspaces.show', $w))->assertNotFound();
    }

    public function test_the_owner_cannot_be_kicked_or_restricted(): void
    {
        $admin = $this->admin();
        $owner = $this->user();
        $w = $this->workspace($owner);
        $ownerRow = $w->membershipOf($owner);

        $this->actingAs($admin)->delete(route('workspaces.members.kick', [$w, $ownerRow]))->assertSessionHasErrors('member');
        $this->actingAs($admin)->post(route('workspaces.members.restrict', [$w, $ownerRow]))->assertSessionHasErrors('member');
        $this->assertNotNull($w->membershipOf($owner));
    }

    public function test_clear_chat_keeps_members_and_delete_workspace_removes_everything(): void
    {
        Storage::fake('local');
        $owner = $this->user();
        $a = $this->user(['email' => 'a@example.com']);
        $w = $this->workspace($owner);
        $this->join($w, $a);

        $this->actingAs($a)->post(route('workspaces.post', $w), ['body' => 'one', 'files' => [UploadedFile::fake()->image('1.jpg')]]);
        $this->actingAs($owner)->post(route('workspaces.post', $w), ['body' => 'two']);
        $path = Note::where('body', 'one')->firstOrFail()->attachments()->firstOrFail()->path;

        $this->actingAs($a)->delete(route('workspaces.clear', $w))->assertNotFound();

        $this->actingAs($owner)->delete(route('workspaces.clear', $w))->assertSessionHasNoErrors();
        $this->assertDatabaseCount('notes', 0);
        $this->assertDatabaseCount('workspace_members', 2);
        Storage::disk('local')->assertMissing($path);

        $this->actingAs($owner)->delete(route('workspaces.destroy', $w))->assertRedirect(route('workspaces.index'));
        $this->assertDatabaseCount('workspaces', 0);
        $this->assertDatabaseCount('workspace_members', 0);
    }

    public function test_workspace_posts_never_count_against_the_public_daily_limit(): void
    {
        $admin = $this->admin();
        $owner = $this->user();
        $w = $this->workspace($owner);
        $this->actingAs($admin)->put(route('admin.settings'), ['public_enabled' => 1, 'daily_limit' => 1]);

        $this->actingAs($owner)->post(route('workspaces.post', $w), ['body' => 'in team']);
        $this->actingAs($owner)->post(route('workspaces.post', $w), ['body' => 'in team again']);
        $this->actingAs($owner)->post(route('public.store'), ['body' => 'public one'])->assertSessionHasNoErrors();
    }

    /* ------------------------------------------------- sign-up with a TAC */

    private function sentCode(): string
    {
        $code = '';
        Mail::assertSent(RegistrationCode::class, function ($mail) use (&$code) {
            $code = $mail->code;

            return true;
        });

        return $code;
    }

    private function signUp(string $email = 'new@example.com'): void
    {
        $this->post(route('register.store'), [
            'name' => 'Newbie', 'email' => $email,
            'password' => 'secret-pass-1', 'password_confirmation' => 'secret-pass-1',
        ]);
    }

    public function test_sign_up_needs_the_emailed_code_and_makes_a_normal_account(): void
    {
        Mail::fake();
        $this->admin(); // sign-up only opens once the first (admin) account exists
        auth()->logout();

        $this->get(route('register'))->assertOk();
        $this->post(route('register.store'), [
            'name' => 'Newbie', 'email' => 'New@Example.com',
            'password' => 'secret-pass-1', 'password_confirmation' => 'secret-pass-1',
        ])->assertRedirect(route('register.verify', ['email' => 'new@example.com']));

        $this->assertDatabaseMissing('users', ['email' => 'new@example.com']); // nothing is created before the code
        $code = $this->sentCode();
        $this->assertMatchesRegularExpression('/^\d{6}$/', $code);

        $this->post(route('register.verify.store'), ['email' => 'new@example.com', 'code' => $code])
            ->assertRedirect(route('notes.index'));

        $user = User::where('email', 'new@example.com')->firstOrFail();
        $this->assertFalse($user->is_admin);
        $this->assertNotNull($user->email_verified_at);
        $this->assertAuthenticatedAs($user);
        $this->assertDatabaseCount('pending_registrations', 0);

        // the typed capitals do not matter at login
        $this->post('/logout');
        $this->post('/login', ['email' => 'New@Example.com', 'password' => 'secret-pass-1'])->assertRedirect(route('notes.index'));
        $this->assertAuthenticated();
    }

    public function test_wrong_codes_burn_the_sign_up_after_five_tries(): void
    {
        Mail::fake();
        $this->admin();
        auth()->logout();
        $this->signUp();
        $code = $this->sentCode();
        $wrong = $code === '111111' ? '222222' : '111111';

        foreach (range(1, 4) as $i) {
            $this->post(route('register.verify.store'), ['email' => 'new@example.com', 'code' => $wrong])->assertSessionHasErrors('code');
        }
        $this->assertDatabaseCount('pending_registrations', 1);

        $this->post(route('register.verify.store'), ['email' => 'new@example.com', 'code' => $wrong])->assertSessionHasErrors('code');
        $this->assertDatabaseCount('pending_registrations', 0);

        // even the right code is useless now
        $this->post(route('register.verify.store'), ['email' => 'new@example.com', 'code' => $code])->assertSessionHasErrors('code');
        $this->assertDatabaseMissing('users', ['email' => 'new@example.com']);
    }

    public function test_an_expired_code_fails_and_a_new_one_can_be_requested(): void
    {
        Mail::fake();
        $this->admin();
        auth()->logout();
        $this->signUp();
        $code = $this->sentCode();

        $this->travel(11)->minutes();
        $this->post(route('register.verify.store'), ['email' => 'new@example.com', 'code' => $code])->assertSessionHasErrors('code');
        $this->assertDatabaseMissing('users', ['email' => 'new@example.com']);

        $this->post(route('register.resend'), ['email' => 'new@example.com'])->assertSessionHasNoErrors();
        $fresh = $this->sentCode();
        $this->post(route('register.verify.store'), ['email' => 'new@example.com', 'code' => $fresh])->assertRedirect(route('notes.index'));
        $this->assertDatabaseHas('users', ['email' => 'new@example.com']);
    }

    public function test_new_codes_cannot_be_requested_more_than_once_a_minute(): void
    {
        Mail::fake();
        $this->admin();
        auth()->logout();
        $this->signUp();

        $this->post(route('register.resend'), ['email' => 'new@example.com'])->assertSessionHasErrors('code');
        Mail::assertSent(RegistrationCode::class, 1);

        $this->travel(61)->seconds();
        $this->post(route('register.resend'), ['email' => 'new@example.com'])->assertSessionHasNoErrors();
        Mail::assertSent(RegistrationCode::class, 2);
    }

    public function test_sign_up_refuses_an_email_that_already_has_an_account(): void
    {
        Mail::fake();
        $this->user(); // me@example.com
        auth()->logout();

        $this->signUp('ME@example.com');

        $this->assertSessionHasErrors('email');
        Mail::assertNothingSent();
    }

    public function test_sign_up_is_closed_on_a_fresh_install_and_when_the_admin_switches_it_off(): void
    {
        $this->get(route('register'))->assertNotFound(); // no admin account yet: /setup comes first

        $admin = $this->admin();
        auth()->logout();
        $this->get('/')->assertSee('Create an account');
        $this->get(route('register'))->assertOk();

        $this->actingAs($admin)->put(route('admin.registration'))->assertSessionHasNoErrors(); // box left unticked
        auth()->logout();
        $this->assertFalse(Setting::bool(Registration::KEY, true));
        $this->get(route('register'))->assertNotFound();
        $this->post(route('register.store'), ['name' => 'X', 'email' => 'x@example.com', 'password' => 'abcdefgh', 'password_confirmation' => 'abcdefgh'])->assertNotFound();
        $this->get('/')->assertDontSee('Create an account');

        $this->actingAs($admin)->put(route('admin.registration'), ['registration_open' => 1]);
        auth()->logout();
        $this->get(route('register'))->assertOk();
    }

    public function test_only_an_admin_can_switch_sign_up(): void
    {
        $this->admin();
        $u = $this->user();

        $this->actingAs($u)->put(route('admin.registration'))->assertNotFound();
        $this->assertTrue(Setting::bool(Registration::KEY, true));
    }

    /* ------------------------------------- pause / suspend / delete accounts */

    public function test_a_paused_account_can_read_but_not_post_reply_or_edit(): void
    {
        $admin = $this->admin();
        $u = $this->user();
        $this->actingAs($admin)->post(route('public.store'), ['body' => 'from admin']);
        $root = Note::where('body', 'from admin')->firstOrFail();
        $mine = $this->note($u, 'my diary');

        $this->actingAs($admin)->post(route('admin.users.pause', $u))->assertSessionHasNoErrors();
        $this->assertTrue($u->fresh()->is_paused);

        $this->actingAs($u->fresh())->get(route('public.index'))->assertOk()->assertSee('from admin')->assertSee('paused');
        $this->actingAs($u->fresh())->get('/home')->assertOk()->assertSee('my diary');

        $this->actingAs($u->fresh())->post(route('public.store'), ['body' => 'hi'])->assertSessionHasErrors('body');
        $this->actingAs($u->fresh())->post('/notes', ['body' => 'new diary'])->assertSessionHasErrors('body');
        $this->actingAs($u->fresh())->post(route('notes.reply', $root), ['body' => 'hi'])->assertSessionHasErrors('body');
        $this->actingAs($u->fresh())->put(route('notes.update', $mine), ['body' => 'changed'])->assertSessionHasErrors('body');
        $this->assertDatabaseMissing('notes', ['body' => 'hi']);
        $this->assertDatabaseMissing('notes', ['body' => 'new diary']);
        $this->assertDatabaseHas('notes', ['body' => 'my diary']);

        $this->actingAs($admin)->post(route('admin.users.pause', $u)); // resume
        $this->actingAs($u->fresh())->post(route('public.store'), ['body' => 'back again'])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('notes', ['body' => 'back again']);
    }

    public function test_a_temporary_suspension_blocks_sign_in_and_lifts_itself(): void
    {
        $admin = $this->admin();
        $u = $this->user();

        $this->actingAs($admin)->post(route('admin.users.suspend', $u), ['days' => 7])->assertSessionHasNoErrors();
        $this->assertTrue($u->fresh()->isSuspendedNow());

        auth()->logout();
        $this->post('/login', ['email' => $u->email, 'password' => 'secret-pass-1'])->assertSessionHasErrors('email');
        $this->assertGuest();

        $this->travel(8)->days();
        $this->assertFalse($u->fresh()->isSuspendedNow());
        $this->post('/login', ['email' => $u->email, 'password' => 'secret-pass-1'])->assertRedirect(route('notes.index'));
        $this->assertAuthenticated();
    }

    public function test_a_suspension_without_a_date_lasts_until_the_admin_reactivates(): void
    {
        $admin = $this->admin();
        $u = $this->user();

        $this->actingAs($admin)->post(route('admin.users.suspend', $u));
        $this->travel(400)->days();
        $this->assertTrue($u->fresh()->isSuspendedNow());

        $this->actingAs($admin)->post(route('admin.users.suspend', $u)); // second press = reactivate
        $this->assertFalse($u->fresh()->isSuspendedNow());
    }

    public function test_deleting_an_account_removes_everything_it_posted(): void
    {
        Storage::fake('local');
        $admin = $this->admin();
        $u = $this->user();
        $other = $this->user(['email' => 'other@example.com', 'name' => 'Other']);

        $this->actingAs($other)->post(route('public.store'), ['body' => 'other root']);
        $otherRoot = Note::where('body', 'other root')->firstOrFail();

        $this->actingAs($u)->post(route('public.store'), ['body' => 'u root', 'files' => [UploadedFile::fake()->image('a.jpg')]]);
        $uRoot = Note::where('body', 'u root')->firstOrFail();
        $path = $uRoot->attachments()->firstOrFail()->path;
        $this->actingAs($other)->post(route('notes.reply', $uRoot), ['body' => 'reply on u root']);
        $this->actingAs($u)->post(route('notes.reply', $otherRoot), ['body' => 'u reply on other']);
        $this->note($u, 'u diary');
        $w = $this->workspace($other);
        $this->join($w, $u);
        $this->actingAs($u)->post(route('workspaces.post', $w), ['body' => 'u in team']);

        $this->actingAs($admin)->delete(route('admin.users.destroy', $u))->assertSessionHasNoErrors();

        $this->assertDatabaseMissing('users', ['id' => $u->id]);
        foreach (['u root', 'reply on u root', 'u reply on other', 'u diary', 'u in team'] as $body) {
            $this->assertDatabaseMissing('notes', ['body' => $body]);
        }
        $this->assertDatabaseHas('notes', ['body' => 'other root']);
        Storage::disk('local')->assertMissing($path);
        $this->assertDatabaseCount('workspace_members', 1); // only the owner is left
    }

    public function test_an_account_cannot_be_deleted_by_itself_by_normal_users_or_while_it_owns_a_workspace(): void
    {
        $admin = $this->admin();
        $owner = $this->user();
        $this->workspace($owner);
        $u = $this->user(['email' => 'u@example.com']);

        $this->actingAs($admin)->delete(route('admin.users.destroy', $admin))->assertSessionHasErrors('user');
        $this->actingAs($admin)->delete(route('admin.users.destroy', $owner))->assertSessionHasErrors('user');
        $this->actingAs($u)->delete(route('admin.users.destroy', $owner))->assertNotFound();

        $this->assertDatabaseHas('users', ['id' => $owner->id]);
        $this->assertDatabaseHas('users', ['id' => $admin->id]);
    }
}
