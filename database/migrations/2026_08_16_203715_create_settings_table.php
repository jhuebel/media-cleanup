<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('settings', function (Blueprint $table) {
            $table->id();
            $table->string('scan_path')->default('');
            $table->json('exclude_patterns')->nullable();
            $table->unsignedInteger('convert_batch_size')->default(250);
            $table->json('convert_extensions')->nullable();
            $table->boolean('mkv_remux')->default(true);
            $table->string('video_codec')->default('libx265');
            $table->unsignedTinyInteger('crf')->default(26);
            $table->string('preset')->default('medium');
            $table->string('tune')->default('ssim');
            $table->string('audio_codec')->default('aac');
            $table->string('audio_bitrate')->default('128k');
            $table->string('delete_marker_filename')->default('deleteafter.txt');
            $table->json('delete_extensions')->nullable();
            $table->timestamps();
        });

        DB::table('settings')->insert([
            'exclude_patterns' => json_encode(['incoming']),
            'convert_extensions' => json_encode(['mkv', 'avi']),
            'delete_extensions' => json_encode(['mp4', 'mkv', 'avi', 'srt', 'sub']),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('settings');
    }
};
