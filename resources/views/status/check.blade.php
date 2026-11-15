@extends('layouts.guest')

@section('title', 'Application status')
@section('icon', 'bi-search')

@push('styles')
<style>
    .box { max-width: 460px; }
    input[type=date] { width: 100%; height: 48px; padding: 0 14px 0 42px; border: 1.5px solid var(--line); border-radius: 12px; font: inherit; font-size: 15px; color: var(--ink); background: #fff; }
    input[type=date]:focus { outline: none; border-color: var(--accent); box-shadow: 0 0 0 4px rgba(22,163,74,.12); }
    input.ref { text-transform: uppercase; letter-spacing: 1px; font-family: ui-monospace, Consolas, monospace; }
    .hint { display: block; margin-top: 6px; font-size: 12px; color: #64748b; }

    .result { margin-top: 22px; border-radius: 16px; padding: 18px; border: 1px solid var(--line); }
    .result.ok { background: #f0fdf4; border-color: #bbf7d0; }
    .result.info { background: #eff6ff; border-color: #bfdbfe; }
    .result.warn { background: #fffbeb; border-color: #fde68a; }
    .result.bad { background: #fef2f2; border-color: #fecaca; }
    .result-head { display: flex; gap: 12px; align-items: center; }
    .result-head > i { width: 44px; height: 44px; border-radius: 12px; display: grid; place-items: center; font-size: 21px; background: #fff; flex-shrink: 0; }
    .result.ok .result-head > i { color: #15803d; }
    .result.info .result-head > i { color: #1d4ed8; }
    .result.warn .result-head > i { color: #b45309; }
    .result.bad .result-head > i { color: #b91c1c; }
    .result-head small { display: block; font-size: 12px; color: #64748b; }
    .result-head b { font-size: 16px; }
    .result p { margin-top: 12px; font-size: 14px; line-height: 1.55; color: var(--text); }

    .track { display: grid; grid-template-columns: repeat(5, 1fr); gap: 4px; margin-top: 16px; }
    .track span { height: 6px; border-radius: 4px; background: #e2e8f0; }
    .track span.on { background: var(--accent); }
    .result.warn .track span.on { background: #f59e0b; }
    .result.bad .track span.on { background: #ef4444; }
    .track-labels { display: grid; grid-template-columns: repeat(5, 1fr); gap: 4px; margin-top: 6px; font-size: 10.5px; color: #64748b; text-align: center; }
</style>
@endpush

@section('content')
    <h1>Hali ya ombi / Application status</h1>
    <p class="sub">Andika namba ya ombi uliyopewa (mf. <b>APP-2026-000145</b>) na tarehe yako ya kuzaliwa.</p>

    @if ($result)
        <div class="result {{ $result['tone'] }}" role="status">
            <div class="result-head">
                <i class="bi {{ $result['icon'] }}"></i>
                <div>
                    <small>{{ $reference }} · {{ mb_substr($person->first_name, 0, 1) }}. {{ $person->last_name }}</small>
                    <b>{{ $result['title'] }}</b>
                </div>
            </div>
            <p>{{ $result['text'] }}</p>
            <div class="track" aria-hidden="true">
                @for ($i = 1; $i <= 5; $i++)<span class="{{ $i <= $result['step'] ? 'on' : '' }}"></span>@endfor
            </div>
            <div class="track-labels" aria-hidden="true"><span>Received</span><span>Review</span><span>Approved</span><span>Card ready</span><span>Collected</span></div>
        </div>
    @endif

    <form method="POST" action="{{ route('status.check') }}" onsubmit="this.querySelector('button').disabled = true">
        @csrf
        <div>
            <label for="reference">Namba ya ombi / Application number</label>
            <div class="control">
                <i class="bi bi-hash"></i>
                <input type="text" id="reference" name="reference" value="{{ old('reference', $reference ?? '') }}" required maxlength="20" autocomplete="off"
                       class="ref {{ $errors->has('reference') ? 'is-invalid' : '' }}" placeholder="APP-2026-000145">
            </div>
            @error('reference')<span class="err">{{ $message }}</span>@enderror
            <span class="hint">Iko kwenye SMS au karatasi uliyopewa wakati wa usajili.</span>
        </div>
        <div>
            <label for="date_of_birth">Tarehe ya kuzaliwa / Date of birth</label>
            <div class="control">
                <i class="bi bi-calendar-event"></i>
                <input type="date" id="date_of_birth" name="date_of_birth" value="{{ old('date_of_birth') }}" required max="{{ now()->toDateString() }}" class="{{ $errors->has('date_of_birth') ? 'is-invalid' : '' }}">
            </div>
            @error('date_of_birth')<span class="err">{{ $message }}</span>@enderror
        </div>
        <button class="btn"><i class="bi bi-search"></i> Angalia / Check status</button>
    </form>

    <p class="links"><a href="{{ route('login') }}"><i class="bi bi-person-badge"></i> Staff sign in</a></p>
@endsection
