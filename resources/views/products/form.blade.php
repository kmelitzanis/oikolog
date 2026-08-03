@extends('layouts.app')
@section('title', isset($product) ? __('messages.edit_product') : __('messages.add_product'))

{{--
    Product create / edit — the manual door into the catalogue, for what has no
    barcode or what the API got wrong. Same section layout as the bill and
    income forms.
--}}

@section('content')
    @php
        $editing = isset($product);
        $nutrition = old('nutrition', $editing ? ($product->nutrition ?? []) : []);
        $nutrients = [
            'calories' => 'kcal', 'protein' => 'g', 'carbs' => 'g', 'sugar' => 'g',
            'fat' => 'g', 'saturated_fat' => 'g', 'fiber' => 'g', 'salt' => 'g',
        ];
    @endphp

    <div class="max-w-3xl">

        <div class="flex items-center gap-3 mb-6">
            <a href="{{ $editing ? route('products.show', $product) : route('products.index') }}"
               class="text-gray-400 dark:text-slate-500 hover:text-gray-600 dark:hover:text-slate-300 transition shrink-0">
                <span class="material-icons-round">arrow_back</span>
            </a>
            <div class="min-w-0">
                <h1 class="text-2xl font-extrabold text-gray-900 dark:text-white truncate">
                    {{ $editing ? __('messages.edit_product') : __('messages.add_product') }}
                </h1>
                @if($editing)
                    <p class="text-sm text-gray-400 dark:text-slate-500 mt-0.5 truncate">{{ $product->name }}</p>
                @endif
            </div>
        </div>

        @if($errors->any())
            <div class="mb-4 rounded-2xl border border-red-200 dark:border-red-900/50 bg-red-50 dark:bg-red-900/20 px-4 py-3">
                <div class="flex items-center gap-2 text-sm font-semibold text-red-700 dark:text-red-300">
                    <span class="material-icons-round text-base">error_outline</span>
                    {{ __('messages.validation_failed') }}
                </div>
            </div>
        @endif

        <form method="POST" class="space-y-4"
              action="{{ $editing ? route('products.update', $product) : route('products.store') }}">
            @csrf
            @if($editing) @method('PUT') @endif

            {{-- ── Basics ───────────────────────────────────────────────── --}}
            <x-form-section icon="shopping_basket"
                            :title="__('messages.section_basics')"
                            :hint="__('messages.section_product_basics_hint')">

                <x-field :label="__('messages.product_name')" name="name" required>
                    <x-input name="name" id="name" required maxlength="255"
                             :invalid="$errors->has('name')"
                             value="{{ old('name', $editing ? $product->name : '') }}"
                             placeholder="{{ __('messages.product_name_ph') }}" />
                </x-field>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <x-field :label="__('messages.brand')" name="brand" optional>
                        <x-input name="brand" id="brand" maxlength="100"
                                 value="{{ old('brand', $editing ? $product->brand : '') }}" />
                    </x-field>

                    <x-field :label="__('messages.barcode')" name="barcode" optional
                             :hint="__('messages.barcode_hint')">
                        <x-input name="barcode" id="barcode" maxlength="50" inputmode="numeric"
                                 :invalid="$errors->has('barcode')"
                                 value="{{ old('barcode', $editing ? $product->barcode : '') }}" />
                    </x-field>

                    <x-field :label="__('messages.category')" name="category" optional>
                        <x-input name="category" id="category" maxlength="100"
                                 value="{{ old('category', $editing ? $product->category : '') }}" />
                    </x-field>

                    <x-field :label="__('messages.unit')" name="unit">
                        <x-input as="select" name="unit" id="unit">
                            @foreach(\App\Support\Units::options() as $key => $label)
                                <option value="{{ $key }}"
                                    {{ old('unit', $editing ? $product->unit : 'piece') === $key ? 'selected' : '' }}>
                                    {{ $label }}
                                </option>
                            @endforeach
                        </x-input>
                    </x-field>

                    <x-field :label="__('messages.net_quantity')" name="net_quantity" optional
                             :hint="__('messages.net_quantity_hint')">
                        <x-input name="net_quantity" id="net_quantity" maxlength="60" placeholder="500 g"
                                 value="{{ old('net_quantity', $editing ? $product->net_quantity : '') }}" />
                    </x-field>

                    <x-field :label="__('messages.serving_size')" name="serving_size" optional>
                        <x-input name="serving_size" id="serving_size" maxlength="60" placeholder="30 g"
                                 value="{{ old('serving_size', $editing ? $product->serving_size : '') }}" />
                    </x-field>
                </div>

                <x-field :label="__('messages.image_url')" name="image_url" optional>
                    <x-input type="url" name="image_url" id="image_url" maxlength="500"
                             :invalid="$errors->has('image_url')"
                             placeholder="https://…"
                             value="{{ old('image_url', $editing ? $product->image_url : '') }}" />
                </x-field>
            </x-form-section>

            {{-- ── Nutrition ────────────────────────────────────────────── --}}
            <x-form-section icon="monitor_heart"
                            :title="__('messages.nutrition_facts')"
                            :hint="__('messages.section_nutrition_hint')">

                <x-field :label="__('messages.nutri_score')" name="nutri_score">
                    <div class="flex flex-wrap items-center gap-2">
                        <label class="cursor-pointer">
                            <input type="radio" name="nutri_score" value="" class="sr-only peer"
                                   {{ old('nutri_score', $editing ? $product->nutri_score : '') ? '' : 'checked' }}>
                            <span class="inline-flex items-center h-8 px-3 rounded-lg border border-gray-200 dark:border-slate-600 text-xs font-semibold text-gray-500 dark:text-slate-400
                                         peer-checked:border-amber-500 peer-checked:bg-amber-50 dark:peer-checked:bg-amber-500/15">
                                {{ __('messages.nutri_score_unknown') }}
                            </span>
                        </label>
                        @foreach(['a','b','c','d','e'] as $g)
                            <label class="cursor-pointer">
                                <input type="radio" name="nutri_score" value="{{ $g }}" class="sr-only peer"
                                       {{ old('nutri_score', $editing ? $product->nutri_score : '') === $g ? 'checked' : '' }}>
                                <span class="inline-block rounded-lg peer-checked:ring-2 peer-checked:ring-offset-2 peer-checked:ring-gray-400 dark:peer-checked:ring-offset-slate-800">
                                    <x-nutri-score :grade="$g" size="md" />
                                </span>
                            </label>
                        @endforeach
                    </div>
                </x-field>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <x-field :label="__('messages.eco_score')" name="eco_score" optional>
                        <x-input as="select" name="eco_score" id="eco_score">
                            <option value="">—</option>
                            @foreach(['a','b','c','d','e'] as $g)
                                <option value="{{ $g }}" {{ old('eco_score', $editing ? $product->eco_score : '') === $g ? 'selected' : '' }}>
                                    {{ strtoupper($g) }}
                                </option>
                            @endforeach
                        </x-input>
                    </x-field>

                    <x-field :label="__('messages.nova_group')" name="nova_group" optional
                             :hint="__('messages.nova_hint')">
                        <x-input as="select" name="nova_group" id="nova_group">
                            <option value="">—</option>
                            @foreach([1,2,3,4] as $n)
                                <option value="{{ $n }}" {{ (string) old('nova_group', $editing ? $product->nova_group : '') === (string) $n ? 'selected' : '' }}>
                                    {{ $n }} · {{ __('messages.nova_' . $n) }}
                                </option>
                            @endforeach
                        </x-input>
                    </x-field>
                </div>

                <x-field :label="__('messages.per_100g')">
                    <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
                        @foreach($nutrients as $key => $suffix)
                            <div class="min-w-0">
                                <label for="nutrition_{{ $key }}" class="block text-xs text-gray-500 dark:text-slate-400 mb-1 truncate">
                                    {{ __('messages.nutrient_' . $key) }} ({{ $suffix }})
                                </label>
                                <x-input type="number" step="0.1" min="0"
                                         name="nutrition[{{ $key }}]" id="nutrition_{{ $key }}"
                                         class="!px-3 !py-2"
                                         value="{{ $nutrition[$key] ?? '' }}" />
                            </div>
                        @endforeach
                    </div>
                </x-field>

                <x-field :label="__('messages.product_ingredients')" name="ingredients_text" optional>
                    <x-input as="textarea" name="ingredients_text" id="ingredients_text" rows="3">{{ old('ingredients_text', $editing ? $product->ingredients_text : '') }}</x-input>
                </x-field>

                <x-field :label="__('messages.notes')" name="description" optional>
                    <x-input as="textarea" name="description" id="description" rows="2">{{ old('description', $editing ? $product->description : '') }}</x-input>
                </x-field>
            </x-form-section>

            <div class="flex gap-3 pt-1">
                <x-btn type="submit" size="lg" class="flex-1" :icon="$editing ? 'save' : 'add'">
                    {{ $editing ? __('messages.save_changes') : __('messages.add_product') }}
                </x-btn>
                <x-btn variant="ghost" size="lg" :href="$editing ? route('products.show', $product) : route('products.index')">
                    {{ __('messages.cancel') }}
                </x-btn>
            </div>
        </form>
    </div>
@endsection
