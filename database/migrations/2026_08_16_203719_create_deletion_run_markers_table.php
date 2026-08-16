<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('deletion_run_markers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('deletion_run_id')->constrained()->cascadeOnDelete();
            $table->string('marker_path');
            $table->integer('delete_after_days')->nullable();
            $table->string('status')->default('ok');
            $table->unsignedInteger('files_deleted_count')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('deletion_run_markers');
    }
};
