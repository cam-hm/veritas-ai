<?php

namespace App\Http\Controllers;

use App\Models\Document;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ChatController extends Controller
{
    use AuthorizesRequests;

    public function general(): View
    {
        return view('pages.chat.general');
    }

    public function show(Document $document): View
    {
        // Ensure the user owns this document
        $this->authorize('view', $document);

        return view('pages.chat.show', ['document' => $document]);
    }
}