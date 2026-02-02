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
        // Update existing users to set company_id to null if their company has been deleted
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['company_id']);
            $table->foreign('company_id')->nullable()->references('id')->on('companies')->onDelete('set null')->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Revert the company_id foreign key constraint to cascade on delete
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['company_id']);
            $table->foreign('company_id')->nullable()->references('id')->on('companies')->onDelete('cascade')->change();
        });
    }
};
