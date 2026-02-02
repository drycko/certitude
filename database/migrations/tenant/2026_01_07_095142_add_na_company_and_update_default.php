<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use App\Models\Company;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Add is_system_default flag to companies table
        Schema::table('companies', function (Blueprint $table) {
            $table->boolean('is_system_default')->default(false)->after('is_active');
        });

        // Create N/A company if it doesn't exist
        $naCompany = Company::firstOrCreate(
            ['code' => 'NA0000'],
            [
                'name' => 'N/A',
                'address' => 'N/A',
                'contact_person' => 'System',
                'email' => 'system@dolesa.co.za',
                'phone' => 'N/A',
                'is_active' => true,
                'is_system_default' => true,
            ]
        );
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Remove the N/A company
        Company::where('code', 'NA0000')->delete();

        // Drop the is_system_default column
        Schema::table('companies', function (Blueprint $table) {
            $table->dropColumn('is_system_default');
        });
    }
};
