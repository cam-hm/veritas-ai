<?php

namespace App\Livewire;

use Livewire\Component;
use Livewire\WithFileUploads;
use App\Models\Document;
use App\Jobs\ProcessDocument;
use Livewire\Attributes\Computed;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

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
        try {
            $this->validate([ 'file' => 'required|file|mimes:pdf,docx,txt,md|max:10240' ]);
            
            // Calculate file hash for deduplication
            $fileHash = hash_file('sha256', $this->file->getRealPath());
            $fileSize = $this->file->getSize();
            
            // Check for duplicate files (same hash and same user)
            $existing = Document::where('file_hash', $fileHash)
                ->where('user_id', Auth::id())
                ->first();
            
            if ($existing) {
                session()->flash('status', 'This document has already been uploaded. (' . $existing->name . ')');
                $this->reset('file');
                $this->fileName = '';
                return;
            }
            
            $path = $this->file->store('documents');
            
            $document = Document::create([
                'user_id' => Auth::id(),
                'name' => $this->file->getClientOriginalName(),
                'path' => $path,
                'file_hash' => $fileHash,
                'file_size' => $fileSize,
                'status' => 'queued',
            ]);
            
            ProcessDocument::dispatch($document);

            $this->reset('file');
            $this->fileName = '';
            session()->flash('status', 'Document uploaded and is now being processed.');

            // 1. Dispatch a browser event to tell Alpine.js we are done.
            $this->dispatch('file-upload-finished');
        } catch (\Illuminate\Validation\ValidationException $e) {
            // Validation errors are handled by Livewire automatically
            // But we can log them for debugging
            Log::warning('Document upload validation failed', [
                'errors' => $e->errors(),
                'user_id' => Auth::id(),
                'file_name' => $this->file?->getClientOriginalName(),
            ]);
            throw $e; // Re-throw to let Livewire handle it
        } catch (\Exception $e) {
            // Log unexpected errors with full context
            $errorMessage = is_array($e->getMessage()) 
                ? json_encode($e->getMessage()) 
                : (string) $e->getMessage();
            
            Log::error('Document upload failed', [
                'error' => $errorMessage,
                'exception_class' => get_class($e),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
                'user_id' => Auth::id(),
                'file_name' => $this->file?->getClientOriginalName(),
                'file_size' => $this->file?->getSize(),
            ]);
            
            session()->flash('error', 'Failed to upload document: ' . $errorMessage);
            throw $e;
        }
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
