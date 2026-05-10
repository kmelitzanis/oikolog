@extends('layouts.app')
@section('title', 'Add Provider')
@section('content')
    <div class="flex items-center gap-3 mb-6">
        <a href="{{ route('admin.providers.index', array_filter(['category_id' => $selectedCatId])) }}"
           class="text-gray-400 dark:text-slate-500 hover:text-gray-600 dark:hover:text-slate-300 transition">
            <span class="material-icons-round text-xl">arrow_back</span>
        </a>
        <h1 class="text-2xl font-extrabold text-gray-900 dark:text-white">Add Provider</h1>
    </div>
    <form method="POST" action="{{ route('admin.providers.store') }}"
          enctype="multipart/form-data"
          class="bg-white dark:bg-slate-800 rounded-2xl border border-gray-100 dark:border-slate-700 shadow-sm p-6 max-w-lg space-y-5">
        @csrf
        {{-- Logo --}}
        <div>
            <label class="block text-sm font-medium text-gray-600 dark:text-slate-300 mb-1.5">
                Logo <span class="text-gray-400 dark:text-slate-500">(optional, image)</span>
            </label>
            <div id="logo-drop-area"
                 class="w-full flex flex-col items-center justify-center border-2 border-dashed border-gray-300 dark:border-slate-600 rounded-xl p-4 bg-gray-50 dark:bg-slate-700 cursor-pointer transition hover:border-indigo-400 mb-2">
                <span id="logo-drop-text" class="text-gray-400 dark:text-slate-500 text-sm mb-2">Drag & drop logo here or click to select</span>
                <input id="logo-input" type="file" name="logo" accept="image/*" class="hidden">
                <img id="logo-preview" src="" alt="Logo preview"
                     class="hidden w-14 h-14 object-contain rounded-xl border border-gray-100 dark:border-slate-600 bg-white dark:bg-slate-700 p-1 mt-2">
            </div>
            <script>
                const dropArea = document.getElementById('logo-drop-area');
                const input = document.getElementById('logo-input');
                const preview = document.getElementById('logo-preview');
                const dropText = document.getElementById('logo-drop-text');
                dropArea.addEventListener('click', () => input.click());
                dropArea.addEventListener('dragover', e => {
                    e.preventDefault();
                    dropArea.classList.add('border-indigo-400');
                });
                dropArea.addEventListener('dragleave', e => {
                    e.preventDefault();
                    dropArea.classList.remove('border-indigo-400');
                });
                dropArea.addEventListener('drop', e => {
                    e.preventDefault();
                    dropArea.classList.remove('border-indigo-400');
                    if (e.dataTransfer.files.length) {
                        input.files = e.dataTransfer.files;
                        showPreview();
                    }
                });
                input.addEventListener('change', showPreview);

                function showPreview() {
                    if (input.files && input.files[0]) {
                        const reader = new FileReader();
                        reader.onload = e => {
                            preview.src = e.target.result;
                            preview.classList.remove('hidden');
                            dropText.classList.add('hidden');
                        };
                        reader.readAsDataURL(input.files[0]);
                    }
                }
            </script>
            @error('logo')<p class="mt-1 text-xs text-red-500">{{ $message }}</p>@enderror
        </div>
        <div>
            <label class="block text-sm font-medium text-gray-600 dark:text-slate-300 mb-1.5">Name *</label>
            <input type="text" name="name" value="{{ old('name') }}" required
                   class="w-full bg-gray-50 dark:bg-slate-700 border border-gray-200 dark:border-slate-600 rounded-xl px-4 py-3 text-sm text-gray-900 dark:text-white outline-none focus:border-indigo-500 focus:ring-2 focus:ring-indigo-100 dark:focus:ring-indigo-900 transition">
            @error('name')<p class="mt-1 text-xs text-red-500">{{ $message }}</p>@enderror
        </div>
        <div>
            <label class="block text-sm font-medium text-gray-600 dark:text-slate-300 mb-2">Categories * <span
                    class="text-gray-400 dark:text-slate-500">(select one or more)</span></label>
            <div
                class="space-y-2 max-h-52 overflow-y-auto border border-gray-200 dark:border-slate-600 rounded-xl p-3 bg-gray-50 dark:bg-slate-700">
                @foreach($categories as $cat)
                    <label class="flex items-center gap-2.5 cursor-pointer select-none group">
                        <input type="checkbox" name="category_ids[]" value="{{ $cat->id }}"
                               class="w-4 h-4 rounded border-gray-300 dark:border-slate-500 text-indigo-600 focus:ring-indigo-500"
                            {{ in_array($cat->id, (array) old('category_ids', $selectedCatId ? [$selectedCatId] : [])) ? 'checked' : '' }}>
                        <span class="material-icons-round text-base"
                              style="color:{{ $cat->color_hex }}">{{ $cat->icon }}</span>
                        <span
                            class="text-sm text-gray-700 dark:text-slate-300 group-hover:text-gray-900 dark:group-hover:text-white">{{ $cat->name }}</span>
                    </label>
                @endforeach
            </div>
            @error('category_ids')<p class="mt-1 text-xs text-red-500">{{ $message }}</p>@enderror
        </div>
        <div>
            <label class="block text-sm font-medium text-gray-600 dark:text-slate-300 mb-1.5">
                Website <span class="text-gray-400 dark:text-slate-500">(optional)</span>
            </label>
            <input type="url" name="website" value="{{ old('website') }}" placeholder="https://example.com"
                   class="w-full bg-gray-50 dark:bg-slate-700 border border-gray-200 dark:border-slate-600 rounded-xl px-4 py-3 text-sm text-gray-900 dark:text-white outline-none focus:border-indigo-500 focus:ring-2 focus:ring-indigo-100 dark:focus:ring-indigo-900 transition">
            @error('website')<p class="mt-1 text-xs text-red-500">{{ $message }}</p>@enderror
        </div>
        <div>
            <label class="block text-sm font-medium text-gray-600 dark:text-slate-300 mb-1.5">
                Phone <span class="text-gray-400 dark:text-slate-500">(optional)</span>
            </label>
            <input type="text" name="phone" value="{{ old('phone') }}" placeholder="+30 210 1234567"
                   class="w-full bg-gray-50 dark:bg-slate-700 border border-gray-200 dark:border-slate-600 rounded-xl px-4 py-3 text-sm text-gray-900 dark:text-white outline-none focus:border-indigo-500 focus:ring-2 focus:ring-indigo-100 dark:focus:ring-indigo-900 transition">
            @error('phone')<p class="mt-1 text-xs text-red-500">{{ $message }}</p>@enderror
        </div>
        <div>
            <label class="block text-sm font-medium text-gray-600 dark:text-slate-300 mb-1.5">
                Notes <span class="text-gray-400 dark:text-slate-500">(optional)</span>
            </label>
            <textarea name="notes" rows="3" placeholder="Any additional notes…"
                      class="w-full bg-gray-50 dark:bg-slate-700 border border-gray-200 dark:border-slate-600 rounded-xl px-4 py-3 text-sm text-gray-900 dark:text-white outline-none focus:border-indigo-500 focus:ring-2 focus:ring-indigo-100 dark:focus:ring-indigo-900 transition resize-none">{{ old('notes') }}</textarea>
            @error('notes')<p class="mt-1 text-xs text-red-500">{{ $message }}</p>@enderror
        </div>
        <div class="flex items-center gap-3 pt-2">
            <button type="submit"
                    class="inline-flex items-center gap-2 bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-semibold rounded-xl px-5 py-2.5 transition">
                <span class="material-icons-round text-lg">save</span> Create Provider
            </button>
            <a href="{{ route('admin.providers.index', array_filter(['category_id' => $selectedCatId])) }}"
               class="text-sm text-gray-500 dark:text-slate-400 hover:text-gray-700 dark:hover:text-slate-200 transition">Cancel</a>
        </div>
    </form>
@endsection
