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
        Schema::create('user_group_user', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('user_group_id')->constrained()->onDelete('cascade');
            $table->json('group_specific_permissions')->nullable(); // Additional permissions for this group
            $table->json('group_specific_restrictions')->nullable(); // Restrictions for this group
            $table->boolean('is_primary_group')->default(false); // Mark user's primary group
            $table->timestamp('assigned_at')->useCurrent();
            $table->timestamp('expires_at')->nullable(); // Optional group membership expiry
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['user_id', 'user_group_id']);
            $table->index(['user_id', 'is_primary_group']);
            $table->index(['user_group_id', 'expires_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_group_user');
    }
};