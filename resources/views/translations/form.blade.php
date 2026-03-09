@extends('layouts.app')
@section('title', isset($translation) ? 'Edit translation' : 'New translation')

@section('content')
    <div style="max-width:720px;">
        <div class="page-header">
            <h1 class="page-title">{{ isset($translation) ? 'Edit translation' : 'New translation' }}</h1>
        </div>

        <form method="POST"
              action="{{ isset($translation) ? route('translations.update', $translation) : route('translations.store') }}">
            @csrf
            @if(isset($translation))
                @method('PUT')
            @endif
            <div class="card" style="padding:16px;">
                <label class="label">Locale</label>
                <input class="input" name="locale" value="{{ old('locale', $translation->locale ?? 'en') }}">
                <label class="label">Group</label>
                <input class="input" name="group" value="{{ old('group', $translation->group ?? 'messages') }}">
                <label class="label">Key</label>
                <input class="input" name="key" value="{{ old('key', $translation->key ?? '') }}">
                <label class="label">Value</label>
                <textarea class="input" name="value" rows="4">{{ old('value', $translation->value ?? '') }}</textarea>
                <div style="display:flex;gap:8px;margin-top:12px;">
                    <button class="btn btn-primary" type="submit">Save</button>
                    <a href="{{ route('translations.index') }}" class="btn btn-secondary">Cancel</a>
                </div>
            </div>
        </form>
    </div>
@endsection

