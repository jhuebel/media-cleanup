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
            $table->string('delete_marker_filename')->default('deleteafter.txt');
            $table->json('delete_extensions')->nullable();
            $table->timestamps();
        });

        DB::table('settings')->insert([
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
