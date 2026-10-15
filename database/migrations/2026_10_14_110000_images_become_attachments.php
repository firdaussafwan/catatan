<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** note_images -> attachments: now holds images, videos and any other file. */
    public function up(): void
    {
        Schema::rename('note_images', 'attachments');

        Schema::table('attachments', function (Blueprint $table) {
            $table->string('kind', 10)->default('image');   // image | video | file   (existing rows are images)
            $table->string('original_name')->nullable();
            $table->string('mime', 100)->nullable();
            $table->unsignedBigInteger('size')->default(0);  // bytes
        });
    }

    public function down(): void
    {
        Schema::table('attachments', function (Blueprint $table) {
            $table->dropColumn(['kind', 'original_name', 'mime', 'size']);
        });

        Schema::rename('attachments', 'note_images');
    }
};
