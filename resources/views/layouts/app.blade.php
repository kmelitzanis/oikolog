<!DOCTYPE html>
<html lang="en" class="h-full">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Dashboard') — BillsTrack</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        primary: { DEFAULT: '#6366f1', dark: '#4f46e5', light: '#818cf8' }
                    }
                }
            }
        }
    </script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons+Round" rel="stylesheet">
    <style>
        body { font-family: 'Inter', sans-serif; }
        .nav-link {
            display: flex; align-items: center; gap: 10px;
            padding: 10px 16px; border-radius: 12px;
            font-size: 14px; font-weight: 500;
            color: #64748b; transition: all 0.15s;
            text-decoration: none;
        }
        .nav-link:hover { background: #eef2ff; color: #4f46e5; }
        .nav-link.active { background: #e0e7ff; color: #4338ca; font-weight: 600; }
        .card { background: #fff; border-radius: 16px; border: 1px solid #f1f5f9; box-shadow: 0 1px 3px rgba(0,0,0,0.04); }
        .btn { display: inline-flex; align-items: center; gap: 6px; padding: 10px 18px; border-radius: 12px; font-size: 14px; font-weight: 600; cursor: pointer; border: none; text-decoration: none; transition: all 0.15s; }
        .btn-primary { background: #6366f1; color: #fff; }
        .btn-primary:hover { background: #4f46e5; }
        .btn-secondary { background: #f1f5f9; color: #475569; }
        .btn-secondary:hover { background: #e2e8f0; }
        .btn-danger { background: #fee2e2; color: #dc2626; }
        .btn-danger:hover { background: #fecaca; }
        .input { width: 100%; background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 12px; padding: 12px 16px; font-size: 14px; outline: none; transition: border 0.15s; font-family: 'Inter', sans-serif; }
        .input:focus { border-color: #6366f1; box-shadow: 0 0 0 3px rgba(99,102,241,0.1); }
        .label { display: block; font-size: 13px; font-weight: 500; color: #475569; margin-bottom: 6px; }
        .badge { display: inline-flex; align-items: center; padding: 2px 10px; border-radius: 20px; font-size: 11px; font-weight: 600; }
        .badge-overdue { background: #fee2e2; color: #dc2626; }
        .badge-active  { background: #d1fae5; color: #059669; }
        .badge-monthly { background: #ede9fe; color: #7c3aed; }
    </style>
    @stack('head')
</head>
<body style="background:#f8fafc; min-height:100vh;">

<div style="display:flex; min-height:100vh;">

    {{-- ── Sidebar ────────────────────────────────────────────────────────── --}}
    <aside style="width:240px; background:#fff; border-right:1px solid #f1f5f9; padding:20px 12px; display:flex; flex-direction:column; position:fixed; top:0; left:0; height:100vh;">

        {{-- Logo --}}
        <div style="display:flex; align-items:center; gap:10px; padding:8px 12px; margin-bottom:24px;">
            <div style="width:36px; height:36px; background:linear-gradient(135deg,#6366f1,#4f46e5); border-radius:10px; display:flex; align-items:center; justify-content:center;">
                <span class="material-icons-round" style="color:#fff; font-size:20px;">account_balance_wallet</span>
            </div>
            <span style="font-size:17px; font-weight:800; color:#0f172a;">BillsTrack</span>
        </div>

        {{-- Nav --}}
        <nav style="flex:1; display:flex; flex-direction:column; gap:4px;">
            <a href="{{ route('dashboard') }}"
               class="nav-link {{ request()->routeIs('dashboard') ? 'active' : '' }}">
                <span class="material-icons-round" style="font-size:20px;">dashboard</span>
                Dashboard
            </a>
            <a href="{{ route('bills.index') }}"
               class="nav-link {{ request()->routeIs('bills.*') ? 'active' : '' }}">
                <span class="material-icons-round" style="font-size:20px;">receipt_long</span>
                Bills
            </a>
            <a href="{{ route('family.index') }}"
               class="nav-link {{ request()->routeIs('family.*') ? 'active' : '' }}">
                <span class="material-icons-round" style="font-size:20px;">group</span>
                Family
            </a>

            @if(auth()->user()?->isFamilyAdmin())
                <div style="margin:16px 0 6px 12px; font-size:10px; font-weight:700; color:#94a3b8; letter-spacing:0.8px; text-transform:uppercase;">Admin</div>
                <a href="{{ route('admin.dashboard') }}"
                   class="nav-link {{ request()->routeIs('admin.*') ? 'active' : '' }}">
                    <span class="material-icons-round" style="font-size:20px;">admin_panel_settings</span>
                    Admin Panel
                </a>
            @endif
        </nav>

        {{-- User --}}
        <div style="border-top:1px solid #f1f5f9; padding-top:16px; margin-top:16px;">
            <div style="display:flex; align-items:center; gap:10px; padding:8px 12px;">
                <div style="width:34px; height:34px; background:#e0e7ff; border-radius:50%; display:flex; align-items:center; justify-content:center; font-weight:700; font-size:14px; color:#4338ca; flex-shrink:0;">
                    {{ strtoupper(substr(auth()->user()?->name ?? '?', 0, 1)) }}
                </div>
                <div style="flex:1; min-width:0;">
                    <div style="font-size:13px; font-weight:600; color:#0f172a; overflow:hidden; text-overflow:ellipsis; white-space:nowrap;">{{ auth()->user()?->name }}</div>
                    <div style="font-size:11px; color:#94a3b8;">{{ auth()->user()?->currency_code }}</div>
                </div>
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit" style="background:none; border:none; cursor:pointer; color:#94a3b8; display:flex;" title="Sign out">
                        <span class="material-icons-round" style="font-size:18px;">logout</span>
                    </button>
                </form>
            </div>
        </div>
    </aside>

    {{-- ── Main Content ───────────────────────────────────────────────────── --}}
    <main style="margin-left:240px; flex:1; padding:32px 36px; max-width:1200px; width:100%;">

        {{-- Flash --}}
        @if(session('success'))
            <div style="background:#d1fae5; border:1px solid #6ee7b7; color:#065f46; border-radius:12px; padding:12px 16px; margin-bottom:20px; font-size:14px; display:flex; align-items:center; gap:8px;">
                <span class="material-icons-round" style="font-size:18px;">check_circle</span>
                {{ session('success') }}
            </div>
        @endif

        @if($errors->any())
            <div style="background:#fee2e2; border:1px solid #fca5a5; color:#991b1b; border-radius:12px; padding:12px 16px; margin-bottom:20px; font-size:14px;">
                @foreach($errors->all() as $error)
                    <div>• {{ $error }}</div>
                @endforeach
            </div>
        @endif

        @yield('content')
    </main>
</div>

@stack('scripts')
</body>
</html>
