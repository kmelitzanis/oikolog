<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sign In — BillsTrack</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons+Round" rel="stylesheet">
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: 'Inter', sans-serif; background: linear-gradient(135deg, #f0f4ff 0%, #faf5ff 100%); min-height: 100vh; display: flex; align-items: center; justify-content: center; padding: 24px; }
        .card { background: #fff; border-radius: 24px; border: 1px solid #e2e8f0; padding: 40px; width: 100%; max-width: 420px; }
        .input { width: 100%; background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 12px; padding: 12px 16px; font-size: 14px; font-family: 'Inter', sans-serif; outline: none; transition: border 0.15s; }
        .input:focus { border-color: #6366f1; box-shadow: 0 0 0 3px rgba(99,102,241,0.1); }
        .btn { width: 100%; background: #6366f1; color: #fff; border: none; border-radius: 12px; padding: 14px; font-size: 15px; font-weight: 600; cursor: pointer; font-family: 'Inter', sans-serif; transition: background 0.15s; }
        .btn:hover { background: #4f46e5; }
        label { display: block; font-size: 13px; font-weight: 500; color: #475569; margin-bottom: 6px; }
    </style>
</head>
<body>
<div class="card">
    {{-- Logo --}}
    <div style="text-align:center; margin-bottom:32px;">
        <div style="width:56px; height:56px; background:linear-gradient(135deg,#6366f1,#4f46e5); border-radius:16px; display:inline-flex; align-items:center; justify-content:center; margin-bottom:16px;">
            <span class="material-icons-round" style="color:#fff; font-size:28px;">account_balance_wallet</span>
        </div>
        <h1 style="font-size:26px; font-weight:800; color:#0f172a;">BillsTrack</h1>
        <p style="font-size:14px; color:#94a3b8; margin-top:4px;">Sign in to your account</p>
    </div>

    {{-- Error --}}
    @if(isset($errors) && $errors->any())
        <div style="background:#fee2e2; border:1px solid #fca5a5; color:#dc2626; border-radius:10px; padding:12px 14px; font-size:13px; margin-bottom:20px;">
            {{ $errors->first() }}
        </div>
    @endif

    <form method="POST" action="{{ route('login.post') }}">
        @csrf
        <div style="margin-bottom:16px;">
            <label>Email</label>
            <input class="input" type="email" name="email" value="{{ old('email') }}" placeholder="you@example.com" required>
        </div>
        <div style="margin-bottom:24px;">
            <label>Password</label>
            <input class="input" type="password" name="password" placeholder="••••••••" required>
        </div>
        <button class="btn" type="submit">Sign In</button>
    </form>

    <p style="text-align:center; font-size:13px; color:#94a3b8; margin-top:24px;">
        Don't have an account?
        <a href="{{ route('register') }}" style="color:#6366f1; font-weight:600; text-decoration:none;">Create one</a>
    </p>
</div>
</body>
</html>
