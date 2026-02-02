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
        Schema::table('user_groups', function (Blueprint $table) {
            // Add created_by column to track who created the group
            $table->foreignId('created_by')->nullable()->constrained('users')->onDelete('set null')->after('sort_order');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('user_groups', function (Blueprint $table) {
            // Drop the created_by column
            $table->dropForeign(['created_by']);
            $table->dropColumn('created_by');
        });
    }
};
