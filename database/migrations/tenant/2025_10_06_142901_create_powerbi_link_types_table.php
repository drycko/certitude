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
        Schema::create('powerbi_link_types', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->string('description')->nullable();
            $table->foreignId('parent_id')->nullable()->constrained('powerbi_link_types')->onDelete('cascade');
            $table->string('attribute_type')->default('none'); // 'customer', 'grower', or 'none'
            $table->boolean('is_active')->default(true);
            $table->json('metadata')->nullable(); // Additional metadata if needed
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('powerbi_link_types');
    }
};
