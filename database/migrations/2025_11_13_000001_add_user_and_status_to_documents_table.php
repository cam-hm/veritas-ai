<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('documents', function (Blueprint $table) {
            // Ownership
            $table->foreignId('user_id')->after('id')->constrained()->cascadeOnDelete();

            // Processing lifecycle fields
            $table->string('status')->default('queued')->after('path');
            $table->timestamp('processed_at')->nullable()->after('status');
            $table->text('error_message')->nullable()->after('processed_at');
            $table->unsignedInteger('num_chunks')->nullable()->after('error_message');
            $table->string('embedding_model')->nullable()->after('num_chunks');
        });
    }

    public function down(): void
    {
        Schema::table('documents', function (Blueprint $table) {
            $table->dropConstrainedForeignId('user_id');
            $table->dropColumn(['status', 'processed_at', 'error_message', 'num_chunks', 'embedding_model']);
        });
    }
};
