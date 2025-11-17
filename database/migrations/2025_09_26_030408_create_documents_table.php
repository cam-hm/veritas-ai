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
        Schema::create('documents', function (Blueprint $table) {
            $table->id();
            
            // Ownership
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            
            // Basic document info
            $table->string('name');
            $table->string('path');
            
            // File metadata
            $table->string('file_hash', 64)->nullable()->index();
            $table->unsignedBigInteger('file_size')->nullable();
            
            // Processing lifecycle fields
            $table->string('status')->default('queued');
            $table->timestamp('processed_at')->nullable();
            $table->text('error_message')->nullable();
            $table->unsignedInteger('num_chunks')->nullable();
            $table->string('embedding_model')->nullable();
            
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('documents');
    }
};