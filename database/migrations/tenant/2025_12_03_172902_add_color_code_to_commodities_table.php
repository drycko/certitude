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
        Schema::table('commodities', function (Blueprint $table) {
            // add color code
            $table->string('color_code')->nullable()->after('description');
            // add icon code
            $table->string('icon_code')->nullable()->after('color_code');
            // add metadata json
            $table->json('metadata')->nullable()->after('icon_code');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('commodities', function (Blueprint $table) {
            // remove color code
            $table->dropColumn('color_code');
            // remove icon code
            $table->dropColumn('icon_code');
            // remove metadata json
            $table->dropColumn('metadata');
        });
    }
};
