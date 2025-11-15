<div class="space-y-6">
    <div class="p-4 sm:p-8 bg-white shadow sm:rounded-lg">
        <div class="max-w-xl">
            <h2 class="text-lg font-medium text-gray-900">
                Upload New Document
            </h2>
            <p class="mt-1 text-sm text-gray-600">
                Upload a PDF, DOCX, or TXT file to be processed.
            </p>

            <form wire:submit.prevent="save"
                  x-data="{ isUploading: false, progress: 0 }"
                  x-on:livewire-upload-start="isUploading = true"
                  x-on:livewire-upload-finish="isUploading = false"
                  x-on:livewire-upload-progress="progress = $event.detail.progress"
                  x-on:file-upload-finished="document.getElementById('file').value = null"
                  class="mt-6 space-y-6">

                <div
                    x-data="{ dragging: false }"
                    x-on:dragover.prevent="dragging = true"
                    x-on:dragleave.prevent="dragging = false"
                    x-on:drop="dragging = false"
                    x-bind:class="{ 'border-indigo-500': dragging }"
                    class="flex items-center justify-center w-full p-6 border-2 border-dashed border-gray-300 rounded-lg text-center"
                >
                    <div class="space-y-2">
                        <svg class="mx-auto h-12 w-12 text-gray-400" stroke="currentColor" fill="none" viewBox="0 0 48 48" aria-hidden="true">
                            <path d="M28 8H12a4 4 0 00-4 4v20m32-12v8m0 0v8a4 4 0 01-4 4H12a4 4 0 01-4-4v-4m32-4l-3.172-3.172a4 4 0 00-5.656 0L28 28M8 32l9.172-9.172a4 4 0 015.656 0L28 28m0 0l4 4m4-24h8m-4-4v8" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
                        </svg>

                        {{-- 3. Use Blade @if to check for the Livewire property --}}
                        @if ($fileName)
                            <p class="text-sm text-gray-500 font-semibold">{{ $fileName }}</p>
                        @else
                            <p class="text-sm text-gray-600">
                                <label for="file" class="font-medium text-indigo-600 hover:text-indigo-500 cursor-pointer">
                                    Click to upload
                                </label> or drag and drop
                            </p>
                            <p class="text-xs text-gray-500">PDF, DOCX, TXT up to 10MB</p>
                        @endif

                        {{-- 4. Remove the Alpine logic from the input --}}
                        <input id="file" wire:model="file" type="file" class="sr-only">
                    </div>
                </div>
                <x-input-error :messages="$errors->get('file')" class="mt-2" />

                <div x-show="isUploading">
                    <div class="w-full bg-gray-200 rounded-full h-2.5">
                        <div class="bg-indigo-600 h-2.5 rounded-full" x-bind:style="{ width: progress + '%' }"></div>
                    </div>
                </div>

                {{-- ADD THIS SECTION BACK --}}
                <div class="flex items-center gap-4">
                    <x-primary-button>{{ __('Save') }}</x-primary-button>

                    @if (session('status'))
                        <p
                            x-data="{ show: true }"
                            x-show="show"
                            x-transition
                            x-init="setTimeout(() => show = false, 5000)"
                            class="text-sm text-green-600"
                        >{{ session('status') }}</p>
                    @endif
                    @if (session('error'))
                        <p
                            x-data="{ show: true }"
                            x-show="show"
                            x-transition
                            x-init="setTimeout(() => show = false, 5000)"
                            class="text-sm text-red-600"
                        >{{ session('error') }}</p>
                    @endif
                </div>
            </form>
        </div>
    </div>

    <div class="p-4 sm:p-8 bg-white shadow sm:rounded-lg" 
         wire:poll.5s="refreshDocuments">
        <div class="flex items-center justify-between mb-6">
            <h2 class="text-lg font-medium text-gray-900">
                Your Documents
            </h2>
            <button wire:click="$refresh" class="text-sm text-indigo-600 hover:text-indigo-500">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 inline-block" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                </svg>
                Refresh
            </button>
        </div>

        <div class="mt-6 space-y-4">
            @forelse ($this->documents as $document)
                <div class="flex items-center justify-between p-4 border rounded-lg {{ $document->status === 'failed' ? 'border-red-300 bg-red-50' : '' }}">
                    <div class="flex items-center gap-4 flex-1">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                        </svg>
                        <div class="flex-1">
                            <p class="font-medium text-gray-900">{{ $document->name }}</p>
                            <div class="flex items-center gap-3 mt-1">
                                <p class="text-sm text-gray-500">Uploaded: {{ $document->created_at->diffForHumans() }}</p>
                                @if($document->file_size)
                                    <span class="text-sm text-gray-500">• {{ $document->formatted_file_size }}</span>
                                @endif
                                @if(in_array($document->status, ['completed', 'processed']) && $document->num_chunks)
                                    <span class="text-sm text-gray-500">• {{ $document->num_chunks }} chunks</span>
                                @endif
                                @if($document->processed_at)
                                    <span class="text-sm text-gray-500">• Processed: {{ $document->processed_at->diffForHumans() }}</span>
                                @endif
                            </div>
                            @if($document->status === 'failed' && $document->error_message)
                                <div class="mt-2 p-2 bg-red-100 border border-red-300 rounded text-sm text-red-800">
                                    <strong>Error:</strong> {{ is_array($document->error_message) ? json_encode($document->error_message) : $document->error_message }}
                                </div>
                            @endif
                        </div>
                    </div>
                    <div class="flex items-center gap-4">
                        @php
                            $statusConfig = match($document->status) {
                                'queued' => ['label' => 'Queued', 'color' => 'yellow', 'bg' => 'bg-yellow-100', 'text' => 'text-yellow-700'],
                                'processing' => ['label' => 'Processing', 'color' => 'blue', 'bg' => 'bg-blue-100', 'text' => 'text-blue-700'],
                                'completed', 'processed' => ['label' => 'Ready', 'color' => 'green', 'bg' => 'bg-green-100', 'text' => 'text-green-700'],
                                'failed' => ['label' => 'Failed', 'color' => 'red', 'bg' => 'bg-red-100', 'text' => 'text-red-700'],
                                default => ['label' => ucfirst($document->status), 'color' => 'gray', 'bg' => 'bg-gray-100', 'text' => 'text-gray-700'],
                            };
                        @endphp
                        <span class="px-2 py-1 text-xs font-medium {{ $statusConfig['text'] }} {{ $statusConfig['bg'] }} rounded-full flex items-center gap-1">
                            @if($document->status === 'processing')
                                <svg class="animate-spin h-3 w-3" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                </svg>
                            @endif
                            {{ $statusConfig['label'] }}
                        </span>
                        @if(in_array($document->status, ['completed', 'processed']))
                            <a href="{{ route('chat.show', $document) }}" wire:navigate>
                                <x-primary-button>{{ __('Chat') }}</x-primary-button>
                            </a>
                        @else
                            <x-primary-button disabled class="opacity-50 cursor-not-allowed">
                                {{ __('Chat') }}
                            </x-primary-button>
                        @endif
                        <x-danger-button wire:click="delete({{ $document->id }})" wire:confirm="Are you sure you want to delete this document?">
                            {{ __('Delete') }}
                        </x-danger-button>
                    </div>
                </div>
            @empty
                <p class="text-gray-500">You haven't uploaded any documents yet.</p>
            @endforelse
        </div>
    </div>
</div>