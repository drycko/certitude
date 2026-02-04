<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // For MySQL, we need to alter the enum column to include new values
        DB::statement("ALTER TABLE file_types MODIFY COLUMN attribute_type ENUM('customer', 'grower', 'admin', 'super-user', 'none') DEFAULT 'none'");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Revert back to original enum values
        DB::statement("ALTER TABLE file_types MODIFY COLUMN attribute_type ENUM('customer', 'grower', 'none') DEFAULT 'none'");
    }
};
