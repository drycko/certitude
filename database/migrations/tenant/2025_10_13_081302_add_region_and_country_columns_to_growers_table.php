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
            // Add region and country columns
            $table->string('region')->nullable()->after('address');
            $table->string('sub_region')->nullable()->after('region');
            $table->string('country')->nullable()->after('sub_region');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('growers', function (Blueprint $table) {
            // Drop the columns if they exist
            if (Schema::hasColumn('growers', 'region')) {
                $table->dropColumn('region');
            }
            if (Schema::hasColumn('growers', 'sub_region')) {
                $table->dropColumn('sub_region');
            }
            if (Schema::hasColumn('growers', 'country')) {
                $table->dropColumn('country');
            }
        });
    }
};
