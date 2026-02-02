<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('database_pool', function (Blueprint $table) {
            $table->id();
            $table->string('database_name')->unique();
            $table->boolean('is_available')->default(true);
            $table->string('assigned_to_tenant')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('database_pool');
    }
};