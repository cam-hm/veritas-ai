<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Pgvector\Laravel\HasNeighbors;
use Pgvector\Laravel\Vector; // Import the Vector class
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DocumentChunk extends Model
{
    use HasFactory;
    use HasNeighbors;

    protected $fillable = ['document_id', 'content', 'embedding'];

    /**
     * Cast the embedding attribute to a Vector object.
     */
    protected $casts = [
        'embedding' => Vector::class,
    ];

    public function document(): BelongsTo
    {
        return $this->belongsTo(Document::class);
    }
}