@extends('layouts.app')
@section('title','Translations')

@section('content')
    <div style="max-width:1000px;">
        <div class="page-header">
            <h1 class="page-title">Translations</h1>
            <a href="{{ route('translations.create') }}" class="btn btn-primary">Add</a>
        </div>

        <div class="card" style="padding:16px;">
            <div style="display:flex;gap:12px;font-weight:700;padding:8px 0;border-bottom:1px solid #f8fafc;">
                <div style="width:80px">Locale</div>
                <div style="width:120px">Group</div>
                <div style="flex:1">Key</div>
                <div style="width:300px">Value</div>
                <div style="width:120px;text-align:right">Actions</div>
            </div>
            @foreach($translations as $t)
                <div style="display:flex;align-items:center;gap:12px;padding:12px 0;border-bottom:1px solid #f8fafc;">
                    <div style="width:80px">{{ $t->locale }}</div>
                    <div style="width:120px">{{ $t->group }}</div>
                    <div style="flex:1">{{ $t->key }}</div>
                    <div style="width:300px;color:#475569">{{ $t->value }}</div>
                    <div style="width:120px;text-align:right;display:flex;justify-content:flex-end;gap:8px;">
                        <a href="{{ route('translations.edit', $t) }}" class="btn btn-secondary">Edit</a>
                        <form method="POST" action="{{ route('translations.destroy', $t) }}"
                              onsubmit="return confirm('Delete?')">
                            @csrf @method('DELETE')
                            <button class="btn btn-danger">Delete</button>
                        </form>
                    </div>
                </div>
            @endforeach

            <div style="margin-top:12px;">{{ $translations->links() }}</div>
        </div>
    </div>
@endsection

