@extends('layouts.app')
@section('title', $recipe->name)

@section('content')
    <div class="max-w-7xl mx-auto">
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 items-start">
            <div class="lg:col-span-2">
                <a href="{{ route('recipes.index') }}"
                   class="text-indigo-600 text-sm font-semibold inline-flex items-center gap-1 mb-3"><span
                            class="material-icons-round">arrow_back</span>Recipes</a>
                <h1 class="text-4xl font-extrabold text-gray-900 dark:text-white mb-3">{{ $recipe->name }}</h1>
                <p class="text-sm text-gray-500 mb-4">Servings: {{ $recipe->servings }}</p>

                <div class="prose max-w-none text-gray-700 dark:text-slate-200 mb-6">
                    <p>{{ $recipe->description }}</p>
                </div>

                <h3 class="text-lg font-semibold mb-3">Directions</h3>
                <div class="bg-white dark:bg-slate-800 p-5 rounded-lg mb-6">
                    <p class="text-sm text-gray-600 dark:text-slate-300">Add directions here (this app currently stores
                        only ingredients). You can expand this area to include steps, timers, and tips.</p>
                </div>

                <h3 class="text-lg font-semibold mb-3">Ingredients</h3>
                <div class="bg-white dark:bg-slate-800 p-5 rounded-lg">
                    <ul class="space-y-3">
                        @foreach($recipe->ingredients as $ing)
                            <li class="flex items-baseline justify-between">
                                <div>
                                    <span class="font-semibold">{{ $ing->quantity }} {{ $ing->unit }}</span>
                                    <span class="text-gray-600 dark:text-slate-300"> — {{ $ing->name }}</span>
                                </div>
                                @if($ing->product)
                                    <div class="text-xs text-indigo-600">linked</div>
                                @endif
                            </li>
                        @endforeach
                    </ul>
                </div>
            </div>

            <aside class="lg:col-span-1 sticky top-20">
                <div class="bg-white dark:bg-slate-800 rounded-2xl p-5">
                    {{-- Placeholder image area --}}
                    <img src="{{ $recipe->image_url ?? asset('images/recipe-placeholder.jpg') }}"
                         alt="{{ $recipe->name }}" class="w-full h-44 object-cover rounded-lg mb-4">

                    <div class="flex items-center justify-between mb-3">
                        <div>
                            <div class="text-sm text-gray-500">Prep</div>
                            <div class="font-semibold">15 min</div>
                        </div>
                        <div>
                            <div class="text-sm text-gray-500">Cook</div>
                            <div class="font-semibold">60 min</div>
                        </div>
                        <div>
                            <div class="text-sm text-gray-500">Total</div>
                            <div class="font-semibold">1h 15m</div>
                        </div>
                    </div>

                    <button id="add-to-list-btn" class="w-full bg-indigo-600 text-white py-2 rounded-lg mb-2">Add
                        ingredients to list
                    </button>
                    <a href="#" class="w-full inline-block text-center bg-slate-100 text-slate-700 py-2 rounded-lg">Adjust</a>
                </div>
            </aside>
        </div>
    </div>
@endsection

@push('scripts')
    <script>
        document.getElementById('add-to-list-btn').addEventListener('click', async function () {
            // Fetch user's shopping lists and let them choose
            const res = await fetch('/api/shopping-lists', {headers: {'Accept': 'application/json'}});
            if (!res.ok) return alert('Failed to load shopping lists');
            const data = await res.json();
            const lists = data.data || data;
            if (!lists.length) return alert('No shopping lists found. Create one first.');
            const choices = lists.map(l => l.name + ' (id:' + l.id + ')').join('\n');
            const pick = prompt('Pick list by id:\n' + choices);
            const id = parseInt(pick);
            if (!id) return;
            // Post each ingredient to the chosen list
            for (const ing of {{ json_encode($recipe->ingredients->map(function($i){return ['name'=>$i->name,'quantity'=>$i->quantity,'unit'=>$i->unit,'product_id'=>$i->product_id];})) }}) {
                try {
                    await fetch(`/api/shopping-lists/${id}/items`, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'Accept': 'application/json',
                            'X-CSRF-TOKEN': document.querySelector('meta[name=\'csrf-token\']').content
                        },
                        body: JSON.stringify({name: ing.name, quantity: ing.quantity, unit: ing.unit, barcode: null})
                    });
                } catch (e) {
                    console.error(e);
                }
            }
            alert('Ingredients added to list.');
        });
    </script>
@endpush


