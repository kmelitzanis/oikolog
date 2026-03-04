@extends('layouts.app')
@section('title', 'Family')

@section('content')

    <div style="max-width:680px;">
        <h1 style="font-size:24px; font-weight:800; color:#0f172a; margin-bottom:24px;">Family</h1>

        @if(!auth()->user()->family_id)

            {{-- No Family --}}
            <div class="card" style="padding:48px; text-align:center;">
                <span class="material-icons-round" style="font-size:64px; color:#c7d2fe; display:block; margin-bottom:16px;">group_add</span>
                <h2 style="font-size:20px; font-weight:700; color:#0f172a; margin-bottom:8px;">No Family Group</h2>
                <p style="font-size:14px; color:#94a3b8; max-width:340px; margin:0 auto 28px;">Create a family group to share bills and track expenses together with your household.</p>
                <div style="display:flex; gap:12px; justify-content:center; flex-wrap:wrap;">
                    <button onclick="document.getElementById('createModal').style.display='flex'" class="btn btn-primary">
                        <span class="material-icons-round" style="font-size:18px;">add</span>
                        Create Family Group
                    </button>
                    <button onclick="document.getElementById('joinModal').style.display='flex'" class="btn btn-secondary">
                        <span class="material-icons-round" style="font-size:18px;">link</span>
                        Join with Code
                    </button>
                </div>
            </div>

            {{-- Create Modal --}}
            <div id="createModal" style="display:none; position:fixed; inset:0; background:rgba(0,0,0,0.4); z-index:100; align-items:center; justify-content:center;">
                <div class="card" style="padding:32px; width:100%; max-width:400px; margin:24px;">
                    <h3 style="font-size:18px; font-weight:700; margin-bottom:20px;">Create Family Group</h3>
                    <form method="POST" action="{{ route('family.create') }}">
                        @csrf
                        <label class="label">Family Name</label>
                        <input class="input" type="text" name="name" placeholder="e.g. The Smith Family" required style="margin-bottom:20px;">
                        <div style="display:flex; gap:10px;">
                            <button class="btn btn-primary" type="submit" style="flex:1; justify-content:center;">Create</button>
                            <button type="button" class="btn btn-secondary" onclick="document.getElementById('createModal').style.display='none'">Cancel</button>
                        </div>
                    </form>
                </div>
            </div>

            {{-- Join Modal --}}
            <div id="joinModal" style="display:none; position:fixed; inset:0; background:rgba(0,0,0,0.4); z-index:100; align-items:center; justify-content:center;">
                <div class="card" style="padding:32px; width:100%; max-width:400px; margin:24px;">
                    <h3 style="font-size:18px; font-weight:700; margin-bottom:20px;">Join Family Group</h3>
                    <form method="POST" action="{{ route('family.join') }}">
                        @csrf
                        <label class="label">Invite Code</label>
                        <input class="input" type="text" name="invite_code" placeholder="e.g. ABCD1234" maxlength="8" style="text-transform:uppercase; letter-spacing:4px; font-size:18px; margin-bottom:20px;" required>
                        <div style="display:flex; gap:10px;">
                            <button class="btn btn-primary" type="submit" style="flex:1; justify-content:center;">Join</button>
                            <button type="button" class="btn btn-secondary" onclick="document.getElementById('joinModal').style.display='none'">Cancel</button>
                        </div>
                    </form>
                </div>
            </div>

        @else

            {{-- Family Info --}}
            <div class="card" style="padding:24px; margin-bottom:16px;">
                <div style="display:flex; align-items:center; justify-content:space-between; margin-bottom:20px;">
                    <h2 style="font-size:18px; font-weight:700; color:#0f172a;">{{ $family->name }}</h2>
                    @if(auth()->user()->isFamilyAdmin())
                        <div style="background:#f0f9ff; border:1px solid #bae6fd; border-radius:10px; padding:8px 14px; display:flex; align-items:center; gap:8px;">
                            <span style="font-size:14px; font-weight:700; color:#0369a1; letter-spacing:3px;">{{ $family->invite_code }}</span>
                            <form method="POST" action="{{ route('family.regenerate') }}">
                                @csrf
                                <button type="submit" style="background:none; border:none; cursor:pointer; color:#0369a1; display:flex;" title="Generate new code">
                                    <span class="material-icons-round" style="font-size:16px;">refresh</span>
                                </button>
                            </form>
                        </div>
                    @endif
                </div>

                {{-- Members --}}
                @foreach($family->members as $member)
                    <div style="display:flex; align-items:center; gap:12px; padding:12px 0; {{ !$loop->last ? 'border-bottom:1px solid #f8fafc;' : '' }}">
                        <div style="width:40px; height:40px; background:#e0e7ff; border-radius:50%; display:flex; align-items:center; justify-content:center; font-weight:700; font-size:15px; color:#4338ca; flex-shrink:0;">
                            {{ strtoupper(substr($member->name, 0, 1)) }}
                        </div>
                        <div style="flex:1;">
                            <div style="font-size:14px; font-weight:600; color:#0f172a;">
                                {{ $member->name }}
                                @if($member->id === auth()->id()) <span style="font-size:11px; color:#94a3b8;">(you)</span> @endif
                            </div>
                            <div style="font-size:12px; color:#94a3b8;">{{ $member->email }}</div>
                        </div>
                        <span class="badge {{ $member->family_role === 'owner' ? 'badge-active' : 'badge-monthly' }}">
                {{ ucfirst($member->family_role) }}
            </span>
                        @if(auth()->user()->isFamilyAdmin() && $member->id !== auth()->id())
                            <form method="POST" action="{{ route('family.remove', $member) }}" onsubmit="return confirm('Remove {{ addslashes($member->name) }} from family?')">
                                @csrf @method('DELETE')
                                <button type="submit" class="btn btn-danger" style="padding:6px 10px;">
                                    <span class="material-icons-round" style="font-size:15px;">person_remove</span>
                                </button>
                            </form>
                        @endif
                    </div>
                @endforeach
            </div>

            {{-- Leave --}}
            @if(!auth()->user()->isFamilyOwner())
                <form method="POST" action="{{ route('family.leave') }}" onsubmit="return confirm('Leave this family group?')">
                    @csrf @method('DELETE')
                    <button class="btn btn-danger" type="submit">
                        <span class="material-icons-round" style="font-size:16px;">exit_to_app</span>
                        Leave Family
                    </button>
                </form>
            @endif

        @endif
    </div>
@endsection
