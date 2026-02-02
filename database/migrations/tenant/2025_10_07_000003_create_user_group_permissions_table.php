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
        Schema::create('user_group_permissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_group_id')->constrained()->onDelete('cascade');
            $table->foreignId('permission_id')->constrained()->onDelete('cascade');
            $table->boolean('is_granted')->default(true); // Allow explicit denials
            $table->json('conditions')->nullable(); // Conditional permissions
            $table->timestamps();

            $table->unique(['user_group_id', 'permission_id']);
            $table->index(['user_group_id', 'is_granted']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_group_permissions');
    }
};