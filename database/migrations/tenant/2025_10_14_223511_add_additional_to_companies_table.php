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
        Schema::table('companies', function (Blueprint $table) {
            // add city, state, country, postal_code, phone_number, website, industry, number_of_employees, company_logo_url and created_by columns
            $table->string('city')->nullable()->after('address');
            $table->string('state')->nullable()->after('city');
            $table->string('country')->nullable()->after('state');
            $table->string('postal_code')->nullable()->after('country');
            $table->string('phone_number')->nullable()->after('postal_code');
            $table->string('website')->nullable()->after('phone_number');
            $table->string('industry')->nullable()->after('website');
            $table->integer('number_of_employees')->nullable()->after('industry');
            $table->string('company_logo_url')->nullable()->after('number_of_employees');
            $table->foreignId('created_by')->nullable()->constrained('users')->onDelete('set null')->after('company_logo_url');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->dropForeign(['created_by']);
            $table->dropColumn('created_by');
            $table->dropColumn('company_logo_url');
            $table->dropColumn('number_of_employees');
            $table->dropColumn('industry');
            $table->dropColumn('website');
            $table->dropColumn('phone_number');
            $table->dropColumn('postal_code');
            $table->dropColumn('country');
            $table->dropColumn('state');
            $table->dropColumn('city');
        });
    }
};
