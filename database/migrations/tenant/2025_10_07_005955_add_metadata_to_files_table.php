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
        Schema::table('files', function (Blueprint $table) {
            $table->json('metadata')->nullable()->after('is_active');
            $table->string('legacy_file_id')->nullable()->after('metadata');
            $table->string('legacy_path')->nullable()->after('legacy_file_id');
            $table->integer('season_year')->nullable()->after('legacy_path');
            $table->integer('sub_file_type_id')->nullable()->after('season_year');
            
            // Indexes for efficient lookups during import and searching
            $table->index('legacy_file_id');
            $table->index('season_year');
            $table->index('sub_file_type_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('files', function (Blueprint $table) {
            $table->dropIndex(['legacy_file_id']);
            $table->dropIndex(['season_year']);
            $table->dropIndex(['sub_file_type_id']);
            $table->dropColumn([
                'metadata',
                'legacy_file_id', 
                'legacy_path',
                'season_year',
                'sub_file_type_id'
            ]);
        });
    }
};
