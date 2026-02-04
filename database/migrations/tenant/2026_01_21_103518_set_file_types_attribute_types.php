<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * Sets appropriate attribute_type values for file types:
     * - 'grower': Only 'Quality Assessment Reports' - visible to grower role users only
     * - 'customer': All other file types - visible to customer role users
     */
    public function up(): void
    {
        // Set ALL file types to 'customer' as default
        DB::table('file_types')
            ->update(['attribute_type' => 'customer']);

        // Set 'Quality Assessment Reports' to grower-only
        DB::table('file_types')
            ->where('name', 'Quality Assessment Reports')
            ->update(['attribute_type' => 'grower']);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Reset all attribute_types to 'customer'
        DB::table('file_types')->update(['attribute_type' => 'customer']);
    }
};
