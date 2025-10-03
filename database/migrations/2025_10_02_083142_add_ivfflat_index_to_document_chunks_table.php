<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // This is raw SQL because the schema builder doesn't support vector indexes yet.
        // It creates an IVFFlat index for Cosine distance searches.
        DB::statement('CREATE INDEX ON document_chunks USING ivfflat (embedding vector_cosine_ops) WITH (lists = 100)');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // The index is automatically named 'document_chunks_embedding_idx'
        Schema::table('document_chunks', function (Blueprint $table) {
            $table->dropIndex('document_chunks_embedding_idx');
        });
    }
};