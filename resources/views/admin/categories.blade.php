@extends('layouts.app')
@section('title', 'Admin — Categories')

@section('content')

    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:24px;">
        <h1 style="font-size:24px; font-weight:800; color:#0f172a;">System Categories</h1>
        <button onclick="document.getElementById('addCatModal').style.display='flex'" class="btn btn-primary">
            <span class="material-icons-round" style="font-size:18px;">add</span> Add Category
        </button>
    </div>

    <div class="card">
        @foreach($categories as $cat)
            <div style="display:flex; align-items:center; gap:14px; padding:14px 20px; {{ !$loop->last ? 'border-bottom:1px solid #f8fafc;' : '' }}">
                <div style="width:40px; height:40px; border-radius:10px; background:{{ $cat->color_hex }}1a; display:flex; align-items:center; justify-content:center;">
                    <span class="material-icons-round" style="color:{{ $cat->color_hex }}; font-size:20px;">{{ $cat->icon }}</span>
                </div>
                <div style="flex:1; font-size:15px; font-weight:600; color:#0f172a;">{{ $cat->name }}</div>
                <code style="font-size:12px; color:#94a3b8; background:#f8fafc; padding:3px 8px; border-radius:6px;">{{ $cat->icon }}</code>
                <div style="width:20px; height:20px; border-radius:6px; background:{{ $cat->color_hex }}; border:1px solid rgba(0,0,0,0.1);"></div>
                <form method="POST" action="{{ route('admin.categories.delete', $cat) }}" onsubmit="return confirm('Delete {{ addslashes($cat->name) }}?')">
                    @csrf @method('DELETE')
                    <button type="submit" class="btn btn-danger" style="padding:6px 10px;">
                        <span class="material-icons-round" style="font-size:15px;">delete</span>
                    </button>
                </form>
            </div>
        @endforeach
    </div>

    {{-- Add Modal --}}
    <div id="addCatModal" style="display:none; position:fixed; inset:0; background:rgba(0,0,0,0.4); z-index:100; align-items:center; justify-content:center;">
        <div class="card" style="padding:32px; width:100%; max-width:420px; margin:24px;">
            <h3 style="font-size:18px; font-weight:700; margin-bottom:20px;">New Category</h3>
            <form method="POST" action="{{ route('admin.categories.store') }}">
                @csrf
                <div style="margin-bottom:14px;">
                    <label class="label">Name</label>
                    <input class="input" type="text" name="name" required>
                </div>
                <div style="margin-bottom:14px;">
                    <label class="label">Icon <span style="color:#94a3b8;">(Material icon name)</span></label>
                    <input class="input" type="text" name="icon" placeholder="e.g. receipt, wifi, home" required>
                </div>
                <div style="margin-bottom:24px;">
                    <label class="label">Color</label>
                    <input class="input" type="color" name="color_hex" value="#6366F1" style="height:44px; padding:4px 8px; cursor:pointer;">
                </div>
                <div style="display:flex; gap:10px;">
                    <button class="btn btn-primary" type="submit" style="flex:1; justify-content:center;">Add</button>
                    <button type="button" class="btn btn-secondary" onclick="document.getElementById('addCatModal').style.display='none'">Cancel</button>
                </div>
            </form>
        </div>
    </div>

@endsection
