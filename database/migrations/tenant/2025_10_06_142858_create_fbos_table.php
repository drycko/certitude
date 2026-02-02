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
        Schema::create('fbos', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique(); // FBO Code assigned by Dept. of Agriculture
            $table->string('name');
            $table->enum('type', ['PUC', 'PHC', 'COC', 'OTHER'])->default('OTHER'); // Production Unit Code, Pack House Code, Chain of Custody, or Other
            $table->string('ggn')->nullable(); // GlobalGAP Number
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->json('metadata')->nullable(); // Legacy data storage
            $table->timestamps();
            $table->softDeletes();
            
            // Indexes for performance
            $table->index(['type', 'is_active']);
            $table->index('ggn');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('fbos');
    }
};