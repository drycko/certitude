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
        Schema::create('powerbi_links', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->text('url');
            $table->text('description')->nullable();
            $table->foreignId('company_id')->nullable()->constrained()->onDelete('set null');
            $table->boolean('is_public')->default(true);
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users')->onDelete('set null');
            $table->foreignId('powerbi_link_type_id')->nullable()->constrained('powerbi_link_types')->onDelete('set null');
            $table->string('link_source')->nullable(); // use for either 'powerbi' or 'other'
            $table->integer('sort_order')->nullable()->default(null);
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('powerbi_links');
    }
};