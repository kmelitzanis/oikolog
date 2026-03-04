@extends('layouts.app')
@section('title', isset($bill) ? 'Edit Bill' : 'Add Bill')

@section('content')

    <div style="max-width:680px;">

        <div style="display:flex; align-items:center; gap:12px; margin-bottom:28px;">
            <a href="{{ route('bills.index') }}" style="color:#94a3b8; display:flex;">
                <span class="material-icons-round">arrow_back</span>
            </a>
            <h1 style="font-size:22px; font-weight:800; color:#0f172a;">
                {{ isset($bill) ? 'Edit Bill' : 'Add New Bill' }}
            </h1>
        </div>

        <form method="POST" action="{{ isset($bill) ? route('bills.update', $bill) : route('bills.store') }}">
            @csrf
            @if(isset($bill)) @method('PUT') @endif

            <div class="card" style="padding:28px; display:flex; flex-direction:column; gap:20px;">

                {{-- Name --}}
                <div>
                    <label class="label">Bill Name *</label>
                    <input class="input" type="text" name="name"
                           value="{{ old('name', isset($bill) ? $bill->name : '') }}"
                           placeholder="e.g. Netflix, Electricity bill" required>
                </div>

                {{-- Amount + Currency --}}
                <div style="display:grid; grid-template-columns:1fr 140px; gap:12px;">
                    <div>
                        <label class="label">Amount *</label>
                        <input class="input" type="number" name="amount" step="0.01" min="0.01"
                               value="{{ old('amount', isset($bill) ? $bill->amount : '') }}"
                               placeholder="0.00" required>
                    </div>
                    <div>
                        <label class="label">Currency *</label>
                        <select class="input" name="currency_code">
                            @foreach(['EUR','USD','GBP','CHF','CAD','AUD','JPY','SEK','NOK','DKK'] as $cur)
                                @php $selectedCurrency = old('currency_code', isset($bill) ? $bill->currency_code : auth()->user()->currency_code); @endphp
                                <option value="{{ $cur }}" {{ $selectedCurrency === $cur ? 'selected' : '' }}>{{ $cur }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>

                {{-- Category --}}
                <div>
                    <label class="label">Category *</label>
                    <select class="input" name="category_id" required>
                        <option value="">Select category...</option>
                        @foreach($categories as $cat)
                            @php $selectedCategory = old('category_id', isset($bill) ? $bill->category_id : ''); @endphp
                            <option value="{{ $cat->id }}" {{ $selectedCategory === $cat->id ? 'selected' : '' }}>
                                {{ $cat->name }}
                            </option>
                        @endforeach
                    </select>
                </div>

                {{-- Frequency --}}
                <div>
                    <label class="label">Frequency *</label>
                    <div style="display:flex; gap:8px; flex-wrap:wrap;">
                        @foreach(['once' => 'One-time', 'weekly' => 'Weekly', 'biweekly' => 'Bi-weekly', 'monthly' => 'Monthly', 'quarterly' => 'Quarterly', 'yearly' => 'Yearly'] as $val => $freqLabel)
                            @php $selected = old('frequency', isset($bill) ? $bill->frequency : 'monthly') === $val; @endphp
                            <label style="cursor:pointer;">
                                <input type="radio" name="frequency" value="{{ $val }}" {{ $selected ? 'checked' : '' }} style="display:none;" class="freq-radio">
                                <span style="display:inline-block; padding:8px 16px; border-radius:10px; font-size:13px; font-weight:500; border:1.5px solid {{ $selected ? '#6366f1' : '#e2e8f0' }}; background:{{ $selected ? '#ede9fe' : '#fff' }}; color:{{ $selected ? '#4338ca' : '#64748b' }}; transition:all 0.15s; user-select:none;">
                                    {{ $freqLabel }}
                                </span>
                            </label>
                        @endforeach
                    </div>
                </div>

                {{-- Dates --}}
                <div style="display:grid; grid-template-columns:1fr 1fr; gap:12px;">
                    <div>
                        <label class="label">Start / First Due Date *</label>
                        <input class="input" type="date" name="start_date"
                               value="{{ old('start_date', isset($bill) ? $bill->start_date->format('Y-m-d') : now()->format('Y-m-d')) }}"
                               required>
                    </div>
                    <div>
                        <label class="label">End Date <span style="color:#94a3b8;">(optional)</span></label>
                        <input class="input" type="date" name="end_date"
                               value="{{ old('end_date', (isset($bill) && $bill->end_date) ? $bill->end_date->format('Y-m-d') : '') }}">
                    </div>
                </div>

                {{-- URL & Notes --}}
                <div>
                    <label class="label">Service URL <span style="color:#94a3b8;">(optional)</span></label>
                    <input class="input" type="url" name="url"
                           value="{{ old('url', isset($bill) ? $bill->url : '') }}"
                           placeholder="https://example.com">
                </div>
                <div>
                    <label class="label">Notes <span style="color:#94a3b8;">(optional)</span></label>
                    <textarea class="input" name="notes" rows="3" placeholder="Any additional notes...">{{ old('notes', isset($bill) ? $bill->notes : '') }}</textarea>
                </div>

                {{-- Sharing --}}
                @if(auth()->user()->family_id)
                    <div style="display:flex; align-items:center; justify-content:space-between; background:#f8fafc; border-radius:12px; padding:14px 16px;">
                        <div>
                            <div style="font-size:14px; font-weight:600; color:#0f172a;">Share with Family</div>
                            <div style="font-size:12px; color:#94a3b8; margin-top:2px;">Visible to all family members</div>
                        </div>
                        <label style="display:flex; align-items:center; cursor:pointer; gap:8px;">
                            <input type="hidden" name="is_shared" value="0">
                            <input type="checkbox" name="is_shared" value="1"
                                   {{ old('is_shared', isset($bill) ? $bill->is_shared : false) ? 'checked' : '' }}
                                   style="width:18px; height:18px; accent-color:#6366f1;">
                        </label>
                    </div>
                @endif

                {{-- Notifications --}}
                @php $notifyEnabled = old('notify_enabled', isset($bill) ? $bill->notify_enabled : true); @endphp
                <div style="background:#f8fafc; border-radius:12px; padding:16px;">
                    <div style="display:flex; align-items:center; justify-content:space-between; margin-bottom:12px;">
                        <div style="font-size:14px; font-weight:600; color:#0f172a;">Due Date Reminder</div>
                        <label style="cursor:pointer;">
                            <input type="hidden" name="notify_enabled" value="0">
                            <input type="checkbox" name="notify_enabled" value="1"
                                   id="notifyToggle"
                                   {{ $notifyEnabled ? 'checked' : '' }}
                                   style="width:18px; height:18px; accent-color:#6366f1;"
                                   onchange="document.getElementById('notifyDays').style.display=this.checked?'block':'none'">
                        </label>
                    </div>
                    <div id="notifyDays" style="{{ $notifyEnabled ? '' : 'display:none;' }}">
                        <label class="label">Remind me before due date</label>
                        <div style="display:flex; gap:8px;">
                            @foreach([1,3,7,14] as $d)
                                @php $selDays = (int) old('notify_days_before', isset($bill) ? $bill->notify_days_before : 3); @endphp
                                <label style="cursor:pointer;">
                                    <input type="radio" name="notify_days_before" value="{{ $d }}"
                                           {{ $selDays === $d ? 'checked' : '' }}
                                           style="display:none;" class="day-radio">
                                    <span style="display:inline-block; padding:7px 14px; border-radius:10px; font-size:13px; font-weight:500; border:1.5px solid {{ $selDays === $d ? '#6366f1' : '#e2e8f0' }}; background:{{ $selDays === $d ? '#ede9fe' : '#fff' }}; color:{{ $selDays === $d ? '#4338ca' : '#64748b' }}; user-select:none;">
                                        {{ $d }}d
                                    </span>
                                </label>
                            @endforeach
                        </div>
                    </div>
                </div>

                {{-- Submit --}}
                <div style="display:flex; gap:12px; padding-top:8px;">
                    <button class="btn btn-primary" type="submit" style="flex:1; justify-content:center; padding:14px;">
                        <span class="material-icons-round" style="font-size:18px;">{{ isset($bill) ? 'save' : 'add' }}</span>
                        {{ isset($bill) ? 'Save Changes' : 'Add Bill' }}
                    </button>
                    <a href="{{ route('bills.index') }}" class="btn btn-secondary" style="padding:14px 20px;">Cancel</a>
                </div>
            </div>
        </form>
    </div>

    @push('scripts')
        <script>
            document.querySelectorAll('.freq-radio').forEach(radio => {
                radio.addEventListener('change', () => {
                    document.querySelectorAll('.freq-radio').forEach(r => {
                        const span = r.nextElementSibling;
                        span.style.borderColor = r.checked ? '#6366f1' : '#e2e8f0';
                        span.style.background  = r.checked ? '#ede9fe' : '#fff';
                        span.style.color       = r.checked ? '#4338ca' : '#64748b';
                    });
                });
            });
            document.querySelectorAll('.day-radio').forEach(radio => {
                radio.addEventListener('change', () => {
                    document.querySelectorAll('.day-radio').forEach(r => {
                        const span = r.nextElementSibling;
                        span.style.borderColor = r.checked ? '#6366f1' : '#e2e8f0';
                        span.style.background  = r.checked ? '#ede9fe' : '#fff';
                        span.style.color       = r.checked ? '#4338ca' : '#64748b';
                    });
                });
            });
        </script>
    @endpush
@endsection
