<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('notes', function (Blueprint $table) {
            $table->timestamp('remind_at')->nullable()->index();   // send a reminder email at this time
            $table->timestamp('reminded_at')->nullable();          // ...and when it was actually sent
            $table->timestamp('due_at')->nullable()->index();      // deadline; email goes out 24h before
            $table->timestamp('due_notified_at')->nullable();      // when the "due soon" email was sent
        });
    }

    public function down(): void
    {
        Schema::table('notes', function (Blueprint $table) {
            $table->dropIndex(['remind_at']);
            $table->dropIndex(['due_at']);
            $table->dropColumn(['remind_at', 'reminded_at', 'due_at', 'due_notified_at']);
        });
    }
};
