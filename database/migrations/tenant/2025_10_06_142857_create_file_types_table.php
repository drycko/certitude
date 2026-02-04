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
        Schema::create('file_types', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->text('description')->nullable();
            $table->foreignId('parent_id')->nullable()->constrained('file_types')->onDelete('cascade');
            $table->enum('attribute_type', ['customer', 'grower', 'none'])->default('none');
            $table->boolean('is_active')->default(true);
            $table->json('metadata')->nullable(); // Legacy data storage
            $table->timestamps();
            $table->softDeletes();
            
            // Index for performance
            $table->index(['parent_id', 'is_active']);
            $table->index('attribute_type');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('file_types');
    }
};