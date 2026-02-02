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
        Schema::create('user_commodity', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('commodity_id')->constrained()->onDelete('cascade');
            
            // Legacy pivot data fields
            $table->integer('legacy_commodity_type_id')->nullable(); // Original legacy ID
            $table->integer('legacy_status_id')->nullable();
            $table->timestamp('legacy_created_date')->nullable();
            $table->timestamp('legacy_last_updated')->nullable();
            
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['user_id', 'commodity_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_commodity');
    }
};