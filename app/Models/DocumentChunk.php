<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Pgvector\Laravel\Vector; // Import the Vector class

class DocumentChunk extends Model
{
    use HasFactory;

    protected $fillable = ['document_id', 'content', 'embedding'];

    /**
     * Cast the embedding attribute to a Vector object.
     */
    protected $casts = [
        'embedding' => Vector::class,
    ];
}