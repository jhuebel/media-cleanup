<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('conversion_files', function (Blueprint $table) {
            $table->unsignedBigInteger('source_size_bytes')->nullable()->after('source_mtime');
            $table->unsignedBigInteger('converted_size_bytes')->nullable()->after('source_size_bytes');
        });
    }

    public function down(): void
    {
        Schema::table('conversion_files', function (Blueprint $table) {
            $table->dropColumn(['source_size_bytes', 'converted_size_bytes']);
        });
    }
};
