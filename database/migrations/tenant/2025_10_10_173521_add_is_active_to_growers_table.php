<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('growers', function (Blueprint $table) {
            // Add a new boolean column 'is_active' with a default value of true
            $table->boolean('is_active')->default(true)->after('notes');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('growers', function (Blueprint $table) {
            // Drop the 'is_active' column if it exists
            $table->dropColumn('is_active');
        });
    }
};
