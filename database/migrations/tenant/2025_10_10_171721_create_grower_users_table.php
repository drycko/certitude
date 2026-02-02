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
        Schema::create('grower_users', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('grower_id');
            $table->unsignedBigInteger('user_id');
            $table->foreign('grower_id')->references('id')->on('growers')->onDelete('cascade');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('grower_users');
    }
};
