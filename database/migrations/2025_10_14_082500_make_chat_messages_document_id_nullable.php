<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('chat_messages', function (Blueprint $table) {
            // Make document_id nullable to support general (global) chat messages
            $table->unsignedBigInteger('document_id')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('chat_messages', function (Blueprint $table) {
            // Revert to not nullable (note: existing nulls must be handled before rollback)
            $table->unsignedBigInteger('document_id')->nullable(false)->change();
        });
    }
};
