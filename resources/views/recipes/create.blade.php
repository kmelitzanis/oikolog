@extends('layouts.app')
@section('title', __('Create Recipe'))

@section('content')
    <div class="max-w-3xl mx-auto">
        <h1 class="text-2xl font-extrabold text-white mb-4">Create Recipe</h1>

        <form method="POST" action="{{ route('recipes.store') }}" x-data="recipeCreate()" @submit.prevent="submit()">
            @csrf
            <div class="bg-slate-800 text-white rounded-2xl border border-slate-700 shadow-lg p-6 space-y-6">
                <div>
                    <label class="block text-sm font-medium text-slate-300 mb-2">Name *</label>
                    <input type="text" x-model="form.name" required
                           class="w-full bg-slate-700 border border-slate-600 rounded-lg px-4 py-3 text-sm outline-none focus:ring-2 focus:ring-indigo-500"/>
                </div>

                <div>
                    <label class="block text-sm font-medium text-slate-300 mb-2">Description</label>
                    <textarea x-model="form.description" rows="4"
                              class="w-full bg-slate-700 border border-slate-600 rounded-lg px-4 py-3 text-sm outline-none"></textarea>
                </div>

                <div>
                    <div class="flex items-center justify-between mb-3">
                        <h3 class="text-sm font-semibold text-slate-200">Ingredients</h3>
                        <div class="flex items-center gap-2">
                            <button type="button" @click.prevent="addIngredient()"
                                    class="bg-indigo-600 hover:bg-indigo-500 text-white px-3 py-1 rounded-full text-sm">
                                Add row
                            </button>
                            <a href="{{ route('admin.products.create') }}" target="_blank"
                               class="bg-slate-700 text-slate-300 px-3 py-1 rounded-full text-sm">Create product</a>
                        </div>
                    </div>

                    <template x-for="(ing, idx) in form.ingredients" :key="idx">
                        <div class="flex items-center gap-2 mb-3">
                            <input type="text" x-model="ing.name" placeholder="Ingredient name"
                                   class="flex-1 bg-slate-700 border border-slate-600 rounded-full px-4 py-2 text-sm"/>
                            <input type="number" step="0.01" x-model="ing.quantity"
                                   class="w-20 bg-slate-700 border border-slate-600 rounded-full px-3 py-2 text-sm"/>
                            <input type="text" x-model="ing.unit" placeholder="unit"
                                   class="w-28 bg-slate-700 border border-slate-600 rounded-full px-3 py-2 text-sm"/>
                            <button type="button" @click.prevent="openProductPicker(idx)"
                                    class="bg-slate-700 text-slate-200 px-3 py-2 rounded-full text-sm">Pick product
                            </button>
                            <button type="button" @click.prevent="removeIngredient(idx)"
                                    class="bg-white text-red-600 px-3 py-2 rounded-full text-sm">Del
                            </button>
                        </div>
                    </template>
                </div>

                <div class="flex gap-4">
                    <button type="submit" :disabled="submitting"
                            class="flex-1 bg-indigo-600 hover:bg-indigo-500 text-white rounded-full py-3 text-sm font-semibold">
                        Save Recipe
                    </button>
                    <a href="{{ route('recipes.index') }}"
                       class="flex-1 bg-slate-700 text-slate-200 rounded-full py-3 text-center text-sm">Cancel</a>
                </div>
            </div>
        </form>
    </div>
@endsection

@push('scripts')
    <script>
        function recipeCreate() {
            return {
                form: {
                    name: '',
                    description: '',
                    servings: 1,
                    ingredients: [{name: '', quantity: 1, unit: 'piece', product_id: null}]
                },
                submitting: false,
                async addIngredient() {
                    this.form.ingredients.push({name: '', quantity: 1, unit: 'piece', product_id: null});
                },
                removeIngredient(i) {
                    this.form.ingredients.splice(i, 1);
                },
                openProductPicker(idx) {
                    // Open /api/products in new window for now — user can copy barcode or name back
                    window.open('/products', '_blank');
                },
                async submit() {
                    this.submitting = true;
                    const res = await fetch('{{ route('recipes.store') }}', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': document.querySelector('meta[name=\'csrf-token\']').content
                        },
                        body: JSON.stringify(this.form)
                    });
                    if (res.ok) {
                        window.location = '{{ route('recipes.index') }}';
                    } else {
                        const err = await res.json();
                        alert('Error: ' + (err.message || 'Failed'));
                    }
                    this.submitting = false;
                }
            }
        }
    </script>
@endpush

