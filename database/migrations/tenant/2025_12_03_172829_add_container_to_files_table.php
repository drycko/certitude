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
            // add container number
            $table->string('container_number')->nullable()->after('sub_file_type_id');
            // add quality rating enum ('Sound', 'Unsound')
            $table->enum('quality_rating', ['Sound', 'Unsound'])->nullable()->after('container_number');
            // add Quality ref number (Qref#)
            $table->string('quality_ref_number')->nullable()->after('quality_rating');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('files', function (Blueprint $table) {
            // remove container number
            $table->dropColumn('container_number');
            // remove quality rating
            $table->dropColumn('quality_rating');
            // remove Quality ref number
            $table->dropColumn('quality_ref_number');
        });
    }
};
