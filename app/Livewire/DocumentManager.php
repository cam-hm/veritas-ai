<?php

namespace App\Livewire;

use Livewire\Component;
use Livewire\WithFileUploads;
use App\Models\Document;
use App\Jobs\ProcessDocument;
use Livewire\Attributes\Computed;
use Illuminate\Support\Facades\Storage;

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
        return Document::latest()->get();
    }

    public function save()
    {
        $this->validate([ 'file' => 'required|file|mimes:pdf,docx,txt,md|max:10240' ]);
        $path = $this->file->store('documents');
        $document = Document::create([ 'name' => $this->file->getClientOriginalName(), 'path' => $path ]);
        ProcessDocument::dispatch($document);

        $this->reset('file');
        $this->fileName = '';
        session()->flash('status', 'Document uploaded and is now being processed.');

        // 1. Dispatch a browser event to tell Alpine.js we are done.
        $this->dispatch('file-upload-finished');
    }

    public function delete($documentId)
    {
        $document = Document::findOrFail($documentId);
        Storage::delete($document->path);
        $document->delete();
        session()->flash('status', 'Document deleted successfully.');
    }

    public function render()
    {
        return view('livewire.pages.documents.form');
    }
}
