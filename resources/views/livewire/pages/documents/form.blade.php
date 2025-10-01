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
                            x-init="setTimeout(() => show = false, 2000)"
                            class="text-sm text-gray-600"
                        >{{ session('status') }}</p>
                    @endif
                </div>
            </form>
        </div>
    </div>

    <div class="p-4 sm:p-8 bg-white shadow sm:rounded-lg">
        <h2 class="text-lg font-medium text-gray-900">
            Your Documents
        </h2>

        <div class="mt-6 space-y-4">
            @forelse ($this->documents as $document)
                <div class="flex items-center justify-between p-4 border rounded-lg">
                    <div class="flex items-center gap-4">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                        </svg>
                        <div>
                            <p class="font-medium text-gray-900">{{ $document->name }}</p>
                            <p class="text-sm text-gray-500">Uploaded: {{ $document->created_at->diffForHumans() }}</p>
                        </div>
                    </div>
                    <div class="flex items-center gap-4">
                        <span class="px-2 py-1 text-xs font-medium text-green-700 bg-green-100 rounded-full">
                            Ready
                        </span>
                        <a href="{{ route('chat.show', $document) }}" wire:navigate>
                            <x-primary-button>{{ __('Chat') }}</x-primary-button>
                        </a>
                        <x-danger-button wire:click="delete({{ $document->id }})">
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