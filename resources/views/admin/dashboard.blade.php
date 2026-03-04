@extends('layouts.app')
@section('title', 'Admin')

@section('content')

    <h1 style="font-size:24px; font-weight:800; color:#0f172a; margin-bottom:24px;">Admin Panel</h1>

    <div style="display:grid; grid-template-columns:repeat(3,1fr); gap:16px; margin-bottom:28px;">
        @foreach(['Users' => [$stats['users'],'people','#6366f1'], 'Bills' => [$stats['bills'],'receipt_long','#10b981'], 'Families' => [$stats['families'],'group','#f59e0b']] as $label => [$val,$icon,$color])
            <div class="card" style="padding:20px;">
                <span class="material-icons-round" style="color:{{ $color }}; font-size:24px;">{{ $icon }}</span>
                <div style="font-size:32px; font-weight:800; color:#0f172a; margin:8px 0 4px;">{{ $val }}</div>
                <div style="font-size:13px; color:#94a3b8;">{{ $label }}</div>
            </div>
        @endforeach
    </div>

    <div class="card" style="padding:24px;">
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:16px;">
            <h2 style="font-size:16px; font-weight:700;">Recent Users</h2>
            <a href="{{ route('admin.users') }}" style="font-size:13px; color:#6366f1; font-weight:600; text-decoration:none;">View all →</a>
        </div>
        @foreach($recentUsers as $user)
            <div style="display:flex; align-items:center; gap:12px; padding:10px 0; {{ !$loop->last ? 'border-bottom:1px solid #f8fafc;' : '' }}">
                <div style="width:36px; height:36px; background:#e0e7ff; border-radius:50%; display:flex; align-items:center; justify-content:center; font-weight:700; font-size:14px; color:#4338ca;">
                    {{ strtoupper(substr($user->name,0,1)) }}
                </div>
                <div style="flex:1;">
                    <div style="font-size:14px; font-weight:600; color:#0f172a;">{{ $user->name }}</div>
                    <div style="font-size:12px; color:#94a3b8;">{{ $user->email }}</div>
                </div>
                <div style="font-size:12px; color:#94a3b8;">{{ $user->created_at->diffForHumans() }}</div>
            </div>
        @endforeach
    </div>

@endsection
