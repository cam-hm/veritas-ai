<?php

namespace App\Http\Controllers;

use App\Models\Document;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ChatController extends Controller
{
    public function show(Document $document): View
    {
        // Optional: Add authorization to ensure the user owns this document
        // $this->authorize('view', $document);

        return view('pages.chat.show', ['document' => $document]);
    }
}