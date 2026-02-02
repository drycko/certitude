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
        Schema::table('powerbi_links', function (Blueprint $table) {
            // Drop the company_id foreign key and column
            $table->dropForeign(['company_id']);
            $table->dropColumn('company_id');
            
            // Drop is_public column
            $table->dropColumn('is_public');
            
            // Add grower_id as nullable with set null on delete
            $table->foreignId('grower_id')->nullable()->after('description')->constrained('growers')->onDelete('set null');
            
            // Add added_by to track which admin user added this report
            $table->foreignId('added_by')->nullable()->after('grower_id')->constrained('users')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('powerbi_links', function (Blueprint $table) {
            // Drop the grower_id foreign key and column
            $table->dropForeign(['grower_id']);
            $table->dropColumn('grower_id');
            
            // Drop added_by foreign key and column
            $table->dropForeign(['added_by']);
            $table->dropColumn('added_by');
            
            // Restore company_id with cascade on delete
            $table->foreignId('company_id')->nullable()->after('description')->constrained()->onDelete('set null');
            
            // Restore is_public column
            $table->boolean('is_public')->default(true)->after('company_id');
        });
    }
};
