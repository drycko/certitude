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
        Schema::create('grower_fbos', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('grower_id');
            $table->unsignedBigInteger('fbo_id');
            $table->foreign('grower_id')->references('id')->on('growers')->onDelete('cascade');
            $table->foreign('fbo_id')->references('id')->on('fbos')->onDelete('cascade');
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('grower_fbos');
    }
};
