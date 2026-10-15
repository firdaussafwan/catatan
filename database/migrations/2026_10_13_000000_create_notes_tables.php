<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // A note is a "post". A reply is a note whose parent_id points to a root note (thread).
        Schema::create('notes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('parent_id')->nullable()->constrained('notes')->cascadeOnDelete();
            $table->text('body')->default('');
            $table->boolean('is_starred')->default(false);
            $table->boolean('is_pinned')->default(false);
            $table->timestamps();

            $table->index(['user_id', 'parent_id', 'created_at']);
            $table->index(['user_id', 'is_starred']);
        });

        Schema::create('note_images', function (Blueprint $table) {
            $table->id();
            $table->foreignId('note_id')->constrained()->cascadeOnDelete();
            $table->string('path');
            $table->unsignedTinyInteger('sort')->default(0);
            $table->timestamps();
        });

        // #hashtags found in a note body
        Schema::create('tags', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name', 40);
            $table->timestamps();

            $table->unique(['user_id', 'name']);
        });

        Schema::create('note_tag', function (Blueprint $table) {
            $table->foreignId('note_id')->constrained()->cascadeOnDelete();
            $table->foreignId('tag_id')->constrained()->cascadeOnDelete();
            $table->primary(['note_id', 'tag_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('note_tag');
        Schema::dropIfExists('tags');
        Schema::dropIfExists('note_images');
        Schema::dropIfExists('notes');
    }
};
