<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('conversion_runs', function (Blueprint $table) {
            $table->timestamp('hidden_at')->nullable()->after('log');
        });

        Schema::table('deletion_runs', function (Blueprint $table) {
            $table->timestamp('hidden_at')->nullable()->after('log');
        });
    }

    public function down(): void
    {
        Schema::table('conversion_runs', function (Blueprint $table) {
            $table->dropColumn('hidden_at');
        });

        Schema::table('deletion_runs', function (Blueprint $table) {
            $table->dropColumn('hidden_at');
        });
    }
};
