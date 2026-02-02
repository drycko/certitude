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
        Schema::create('subscription_plans', function (Blueprint $table) {
            $table->id();
            $table->string('name'); // Starter, Growth, Enterprise
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            
            // Pricing
            $table->decimal('setup_price', 10, 2)->default(0);
            $table->decimal('monthly_price', 10, 2)->default(0);
            $table->decimal('yearly_price', 10, 2)->default(0);
            
            // Limits & Features
            $table->json('features')->nullable(); // List of enabled modules/features
            $table->integer('max_users')->nullable();
            $table->integer('support_hours')->default(0); // Monthly support hours included
            
            // Status
            $table->boolean('is_default')->default(false);
            $table->boolean('is_active')->default(true);
            $table->integer('sort_order')->default(0);
            
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('subscription_plans');
    }
};
