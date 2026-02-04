<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Schema::table('files', function (Blueprint $table) {
        //     $table->dropForeign(['company_id']);
        //     $table->unsignedBigInteger('company_id')->nullable()->change();
        //     $table->foreign('company_id')->references('id')->on('companies')->onDelete('set null');
        // });
    }

    public function down(): void
    {
        // Schema::table('files', function (Blueprint $table) {
        //     $table->dropForeign(['company_id']);
        //     $table->unsignedBigInteger('company_id')->nullable(false)->change();
        //     $table->foreign('company_id')->references('id')->on('companies')->onDelete('cascade');
        // });
    }
};