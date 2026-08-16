<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('deleted_files', function (Blueprint $table) {
            $table->id();
            $table->foreignId('deletion_run_id')->constrained()->cascadeOnDelete();
            $table->foreignId('deletion_run_marker_id')->nullable()->constrained()->nullOnDelete();
            $table->string('path');
            $table->unsignedBigInteger('size_bytes')->default(0);
            $table->timestamp('last_write_time')->nullable();
            $table->timestamp('deleted_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('deleted_files');
    }
};
