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
        Schema::create('files', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('company_id')->constrained()->onDelete('cascade');
            $table->foreignId('file_type_id')->constrained()->onDelete('cascade');
            $table->foreignId('fbo_id')->nullable()->constrained()->onDelete('set null');
            $table->string('title');
            $table->string('filename');
            $table->string('original_filename');
            $table->string('file_path');
            $table->bigInteger('file_size')->unsigned();
            $table->string('mime_type');
            $table->boolean('is_public')->default(false);
            $table->date('expiry_date')->nullable();
            $table->text('description')->nullable();
            $table->foreignId('uploaded_by')->constrained('users')->onDelete('cascade');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['company_id', 'is_public', 'is_active']);
            $table->index(['user_id', 'is_active']);
            $table->index('expiry_date');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('files');
    }
};