<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('is_paused')->default(false);          // read-only: can sign in and read, can not post
            $table->timestamp('suspended_until')->nullable();      // temporary suspension; null + is_suspended = until the admin lifts it
        });

        // Sign-ups wait here until the emailed 6-digit code (TAC) is confirmed. Only then a real account is created.
        Schema::create('pending_registrations', function (Blueprint $table) {
            $table->id();
            $table->string('email')->unique();
            $table->string('name', 80);
            $table->text('password');                              // encrypted with the app key; never stored in clear text
            $table->string('code_hash', 64);                       // HMAC of the code: the code itself is never stored
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->timestamp('expires_at');
            $table->timestamp('last_sent_at');
            $table->string('ip', 45)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pending_registrations');
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['is_paused', 'suspended_until']);
        });
    }
};
