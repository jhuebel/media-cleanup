<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('conversion_files', function (Blueprint $table) {
            $table->id();
            $table->foreignId('conversion_run_id')->constrained()->cascadeOnDelete();
            $table->string('source_path');
            $table->string('extension');
            $table->string('status')->default('pending');
            $table->text('error_message')->nullable();
            $table->timestamp('source_mtime')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('conversion_files');
    }
};
