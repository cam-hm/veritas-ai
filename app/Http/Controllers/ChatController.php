<?php

namespace App\Http\Controllers;

use App\Models\Document;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class ChatController extends Controller
{
    use AuthorizesRequests;

    public function general(): View
    {
        return view('pages.chat.general');
    }

    public function show(Document $document): View|RedirectResponse
    {
        // Ensure the user owns this document
        $this->authorize('view', $document);

        // Check if document is ready for chat (support both 'completed' and legacy 'processed' status)
        if (!in_array($document->status, ['completed', 'processed'])) {
            return redirect()->route('documents.index')
                ->with('error', 'This document is not ready for chat yet. Status: ' . ucfirst($document->status));
        }

        return view('pages.chat.show', ['document' => $document]);
    }
}