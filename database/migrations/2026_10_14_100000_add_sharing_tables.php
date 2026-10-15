<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('is_admin')->default(false);
            $table->boolean('is_suspended')->default(false);      // cannot sign in
            $table->boolean('public_blocked')->default(false);    // cannot post to the Public feed
        });

        // The account that already exists (created by /setup) becomes the admin.
        $firstId = DB::table('users')->min('id');
        if ($firstId) {
            DB::table('users')->where('id', $firstId)->update(['is_admin' => true]);
        }

        // Admin-editable settings (kill switch, daily limit, ...)
        Schema::create('app_settings', function (Blueprint $table) {
            $table->string('key')->primary();
            $table->text('value')->nullable();
        });

        Schema::create('workspaces', function (Blueprint $table) {
            $table->id();
            $table->string('name', 80);
            $table->string('description', 300)->nullable();
            $table->foreignId('owner_id')->constrained('users')->cascadeOnDelete();
            $table->string('join_token', 64)->unique();           // the shareable invite link
            $table->boolean('invite_enabled')->default(true);      // owner can switch the link off
            $table->boolean('is_listed')->default(true);           // visible in the directory (request to join)
            $table->timestamps();
        });

        Schema::create('workspace_members', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('role', 10)->default('member');         // owner | member
            $table->boolean('is_restricted')->default(false);      // may read, may not post
            $table->timestamps();

            $table->unique(['workspace_id', 'user_id']);
        });

        Schema::create('workspace_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['workspace_id', 'user_id']);
        });

        // Where a note lives: private (only me) | public (everyone signed in) | workspace.
        // workspace_id is a plain indexed column (cleaned up in code when a workspace is deleted).
        Schema::table('notes', function (Blueprint $table) {
            $table->string('visibility', 12)->default('private');
            $table->unsignedBigInteger('workspace_id')->nullable();
            $table->index(['visibility', 'workspace_id', 'parent_id', 'created_at'], 'notes_feed_index');
        });
    }

    public function down(): void
    {
        Schema::table('notes', function (Blueprint $table) {
            $table->dropIndex('notes_feed_index');
            $table->dropColumn(['visibility', 'workspace_id']);
        });
        Schema::dropIfExists('workspace_requests');
        Schema::dropIfExists('workspace_members');
        Schema::dropIfExists('workspaces');
        Schema::dropIfExists('app_settings');
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['is_admin', 'is_suspended', 'public_blocked']);
        });
    }
};
