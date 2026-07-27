@extends('layouts.app')
@section('title', __('messages.products'))

@section('content')
    <div class="max-w-5xl">
        <div class="flex items-center justify-between mb-6">
            <h1 class="text-2xl font-extrabold text-gray-900 dark:text-white">{{ __('messages.products') }}</h1>
            <a href="{{ route('admin.products.create') }}"
               class="inline-flex items-center gap-2 bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-semibold rounded-xl px-4 py-2.5 transition">
                <span class="material-icons-round">add</span> Add Product
            </a>
        </div>

        <x-card flush class="overflow-hidden">
            <table class="w-full text-sm">
                <thead class="bg-gray-50 dark:bg-slate-700/50 border-b border-gray-100 dark:border-slate-700">
                <tr>
                    <th class="px-4 py-3 text-left">Product</th>
                    <th class="px-4 py-3 text-left hidden sm:table-cell">Barcode</th>
                    <th class="px-4 py-3 text-left hidden md:table-cell">Nutrition</th>
                    <th class="px-4 py-3 text-left hidden md:table-cell">Scores</th>
                    <th class="px-4 py-3">&nbsp;</th>
                </tr>
                </thead>
                <tbody class="divide-y divide-gray-50 dark:divide-slate-700">
                @forelse($products as $p)
                    <tr>
                        <td class="px-4 py-3">
                            <div class="flex items-center gap-3">
                                @if($p->image_url || $p->image_path)
                                    <img src="{{ $p->image_path ? Storage::disk('public')->url($p->image_path) : $p->image_url }}"
                                         class="w-10 h-10 object-contain rounded-lg shrink-0 bg-gray-50 dark:bg-slate-700 border border-gray-100 dark:border-slate-600"
                                         alt="">
                                @else
                                    <div class="w-10 h-10 rounded-lg bg-gray-100 dark:bg-slate-700 flex items-center justify-center shrink-0">
                                        <span class="material-icons-round text-gray-400 text-lg">inventory_2</span>
                                    </div>
                                @endif
                                <div>
                                    <div class="font-medium text-gray-900 dark:text-white">{{ $p->name }}</div>
                                    @if($p->brand)
                                        <div class="text-xs text-gray-400">{{ $p->brand }}</div>
                                    @endif
                                </div>
                            </div>
                        </td>
                        <td class="px-4 py-3 text-xs text-gray-500 hidden sm:table-cell font-mono">
                            {{ $p->barcode ?? '—' }}
                        </td>
                        <td class="px-4 py-3 hidden md:table-cell">
                            @if($p->nutrition)
                                <div class="flex flex-wrap gap-1">
                                    @foreach(['calories' => 'kcal', 'protein' => 'g', 'carbs' => 'g', 'fat' => 'g'] as $k => $unit)
                                        @if(isset($p->nutrition[$k]))
                                            <span class="bg-gray-100 dark:bg-slate-700 text-gray-600 dark:text-slate-300 rounded-lg px-2 py-0.5 text-xs">
                                                {{ ucfirst($k) }}: {{ $p->nutrition[$k] }}{{ $unit }}
                                            </span>
                                        @endif
                                    @endforeach
                                </div>
                            @else
                                <span class="text-gray-400 text-xs">—</span>
                            @endif
                        </td>
                        <td class="px-4 py-3 hidden md:table-cell">
                            <div class="flex gap-1.5">
                                @if($p->nutri_score)
                                    @php $nColors = ['a'=>'bg-green-500','b'=>'bg-lime-500','c'=>'bg-yellow-400','d'=>'bg-orange-400','e'=>'bg-red-500']; @endphp
                                    <span class="inline-flex items-center justify-center w-7 h-7 rounded-lg text-white text-xs font-bold {{ $nColors[strtolower($p->nutri_score)] ?? 'bg-gray-400' }}">
                                        {{ strtoupper($p->nutri_score) }}
                                    </span>
                                @endif
                                @if($p->eco_score)
                                    <span class="inline-flex items-center justify-center w-7 h-7 rounded-lg bg-teal-600 text-white text-xs font-bold">
                                        {{ strtoupper($p->eco_score) }}
                                    </span>
                                @endif
                            </div>
                        </td>
                        <td class="flex items-center justify-end px-4 py-3 text-right whitespace-nowrap">
                            <a href="{{ route('admin.products.edit', $p) }}"
                               class="inline-flex items-center gap-1 bg-gray-50 dark:bg-slate-700 hover:bg-gray-100 dark:hover:bg-slate-600 text-gray-700 dark:text-slate-200 rounded-xl px-3 py-1.5 text-sm transition">
                                <span class="material-icons-round text-sm">edit</span> Edit
                            </a>
                            <form method="POST" action="{{ route('admin.products.destroy', $p) }}" class="inline-block">
                                @csrf @method('DELETE')
                                <button type="submit" onclick="return confirm('Delete this product?')"
                                        class="ml-1 inline-flex items-center gap-1 bg-red-50 hover:bg-red-100 text-red-600 rounded-xl px-3 py-1.5 text-sm transition">
                                    Delete
                                </button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="px-4 py-8 text-center text-gray-400">No products yet</td>
                    </tr>
                @endforelse
                </tbody>
            </table>
        </x-card>

        <div class="mt-6">{{ $products->links() }}</div>
    </div>
@endsection



