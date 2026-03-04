<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register — BillsTrack</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons+Round" rel="stylesheet">
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: 'Inter', sans-serif; background: linear-gradient(135deg, #f0f4ff 0%, #faf5ff 100%); min-height: 100vh; display: flex; align-items: center; justify-content: center; padding: 24px; }
        .card { background: #fff; border-radius: 24px; border: 1px solid #e2e8f0; padding: 40px; width: 100%; max-width: 440px; }
        .input { width: 100%; background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 12px; padding: 12px 16px; font-size: 14px; font-family: 'Inter', sans-serif; outline: none; transition: border 0.15s; }
        .input:focus { border-color: #6366f1; box-shadow: 0 0 0 3px rgba(99,102,241,0.1); }
        .btn { width: 100%; background: #6366f1; color: #fff; border: none; border-radius: 12px; padding: 14px; font-size: 15px; font-weight: 600; cursor: pointer; font-family: 'Inter', sans-serif; transition: background 0.15s; }
        .btn:hover { background: #4f46e5; }
        label { display: block; font-size: 13px; font-weight: 500; color: #475569; margin-bottom: 6px; }
        .row { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }
    </style>
</head>
<body>
<div class="card">
    <div style="text-align:center; margin-bottom:32px;">
        <div style="width:56px; height:56px; background:linear-gradient(135deg,#6366f1,#4f46e5); border-radius:16px; display:inline-flex; align-items:center; justify-content:center; margin-bottom:16px;">
            <span class="material-icons-round" style="color:#fff; font-size:28px;">account_balance_wallet</span>
        </div>
        <h1 style="font-size:26px; font-weight:800; color:#0f172a;">Create Account</h1>
        <p style="font-size:14px; color:#94a3b8; margin-top:4px;">Start tracking your household bills</p>
    </div>

    @if($errors->any())
        <div style="background:#fee2e2; border:1px solid #fca5a5; color:#dc2626; border-radius:10px; padding:12px 14px; font-size:13px; margin-bottom:20px;">
            @foreach($errors->all() as $e) <div>• {{ $e }}</div> @endforeach
        </div>
    @endif

    <form method="POST" action="{{ route('register.post') }}">
        @csrf
        <div style="margin-bottom:16px;">
            <label>Full Name</label>
            <input class="input" type="text" name="name" value="{{ old('name') }}" placeholder="Your name" required>
        </div>
        <div style="margin-bottom:16px;">
            <label>Email</label>
            <input class="input" type="email" name="email" value="{{ old('email') }}" placeholder="you@example.com" required>
        </div>
        <div class="row" style="margin-bottom:16px;">
            <div>
                <label>Password</label>
                <input class="input" type="password" name="password" placeholder="Min 8 chars" required>
            </div>
            <div>
                <label>Confirm</label>
                <input class="input" type="password" name="password_confirmation" placeholder="Repeat" required>
            </div>
        </div>
        <div style="margin-bottom:28px;">
            <label>Default Currency</label>
            <select class="input" name="currency_code">
                <option value="EUR" {{ old('currency_code','EUR')=='EUR'?'selected':'' }}>EUR — Euro</option>
                <option value="USD" {{ old('currency_code')=='USD'?'selected':'' }}>USD — US Dollar</option>
                <option value="GBP" {{ old('currency_code')=='GBP'?'selected':'' }}>GBP — British Pound</option>
                <option value="CHF" {{ old('currency_code')=='CHF'?'selected':'' }}>CHF — Swiss Franc</option>
                <option value="CAD" {{ old('currency_code')=='CAD'?'selected':'' }}>CAD — Canadian Dollar</option>
                <option value="AUD" {{ old('currency_code')=='AUD'?'selected':'' }}>AUD — Australian Dollar</option>
            </select>
        </div>
        <button class="btn" type="submit">Create Account</button>
    </form>

    <p style="text-align:center; font-size:13px; color:#94a3b8; margin-top:24px;">
        Already have an account?
        <a href="{{ route('login') }}" style="color:#6366f1; font-weight:600; text-decoration:none;">Sign In</a>
    </p>
</div>
</body>
</html>
