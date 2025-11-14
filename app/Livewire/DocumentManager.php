<?php

namespace App\Livewire;

use Livewire\Component;
use Livewire\WithFileUploads;
use App\Models\Document;
use App\Jobs\ProcessDocument;
use Livewire\Attributes\Computed;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Auth;

class DocumentManager extends Component
{
    use WithFileUploads;

    public $file;
    public string $fileName = ''; // 1. Add a public property for the file name

    /**
     * This is a Livewire lifecycle hook. It runs automatically
     * whenever the $file property is updated.
     */
    public function updatedFile(): void
    {
        $this->fileName = $this->file ? $this->file->getClientOriginalName() : '';
    }

    #[Computed]
    public function documents()
    {
        return Document::query()
            ->where('user_id', Auth::id())
            ->latest()
            ->get();
    }

    /**
     * Check if any documents are still processing (queued or processing status)
     * This helps optimize polling - only poll when needed
     */
    #[Computed]
    public function hasProcessingDocuments()
    {
        return Document::query()
            ->where('user_id', Auth::id())
            ->whereIn('status', ['queued', 'processing'])
            ->exists();
    }

    /**
     * Refresh the documents list - used for polling
     * This method is called by wire:poll to update the UI
     * It will continue polling as long as there are documents being processed
     */
    public function refreshDocuments()
    {
        // Clear the computed cache to force recalculation
        unset($this->documents);
        unset($this->hasProcessingDocuments);
        
        // Access the properties to trigger recalculation
        $this->documents;
        $this->hasProcessingDocuments;
        
        // The polling will continue automatically - Livewire handles this
        // When all documents are in final states, the UI will update but polling continues
        // This is fine as it's lightweight and ensures real-time updates
    }

    public function save()
    {
        $this->validate([ 'file' => 'required|file|mimes:pdf,docx,txt,md|max:10240' ]);
        $path = $this->file->store('documents');
        $document = Document::create([
            'user_id' => Auth::id(),
            'name' => $this->file->getClientOriginalName(),
            'path' => $path,
            'status' => 'queued',
        ]);
        ProcessDocument::dispatch($document);

        $this->reset('file');
        $this->fileName = '';
        session()->flash('status', 'Document uploaded and is now being processed.');

        // 1. Dispatch a browser event to tell Alpine.js we are done.
        $this->dispatch('file-upload-finished');
    }

    public function delete($documentId)
    {
        $document = Document::where('user_id', Auth::id())->findOrFail($documentId);
        Storage::delete($document->path);
        $document->delete();
        session()->flash('status', 'Document deleted successfully.');
    }

    public function render()
    {
        return view('livewire.pages.documents.form');
    }
}
