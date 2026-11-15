@extends('layouts.app')

@section('title', 'My Account')
@section('page_title', 'My Account')

@php
    $pb = $errors->getBag('profile');
    $wb = $errors->getBag('password');
@endphp

@push('styles')
<style>
    .acc-hero { display: flex; align-items: center; gap: 18px; flex-wrap: wrap; }
    .acc-avatar { width: 72px; height: 72px; border-radius: 20px; display: grid; place-items: center; font-size: 28px; font-weight: 800; background: linear-gradient(135deg, #16a34a, #15803d); color: #fff; box-shadow: 0 12px 24px -12px rgba(21,128,61,.7); }
    .acc-hero h3 { margin: 0; font-size: 20px; color: var(--ink); }
    .acc-hero p { margin: 3px 0 8px; color: var(--muted); font-size: 13.5px; }
    .acc-nums { display: flex; gap: 10px; margin-left: auto; }
    .acc-nums div { text-align: center; background: #f8fafc; border-radius: 12px; padding: 10px 16px; }
    .acc-nums b { display: block; font-size: 20px; color: var(--ink); }
    .acc-nums small { font-size: 11.5px; color: var(--muted); }
    .mini-log { display: flex; flex-direction: column; }
    .mini-log div { display: flex; gap: 10px; padding: 10px 0; border-bottom: 1px dashed var(--border); font-size: 13px; }
    .mini-log div:last-child { border-bottom: 0; }
    .mini-log i { width: 28px; height: 28px; border-radius: 8px; display: grid; place-items: center; flex-shrink: 0; font-size: 13px; }
    .mini-log span { color: var(--text); }
    .mini-log small { display: block; color: var(--muted); font-size: 11.5px; }
    .pw-meter { height: 6px; border-radius: 999px; background: #f1f5f9; overflow: hidden; margin-top: 2px; }
    .pw-meter span { display: block; height: 100%; width: 0; transition: width .2s, background .2s; }
</style>
@endpush

@section('content')
<div class="ui-page">

    <section class="ui-card">
        <div class="acc-hero">
            <span class="acc-avatar">{{ mb_strtoupper(mb_substr($user->name, 0, 1)) }}</span>
            <div>
                <h3>{{ $user->name }}</h3>
                <p>{{ $user->email }}</p>
                <span class="ui-pill violet">{{ $user->role }}</span>
                <span class="ui-pill">Last sign-in {{ $user->last_login_at?->diffForHumans() ?? 'n/a' }}</span>
            </div>
            <div class="acc-nums">
                <div><b>{{ $counts['registered'] }}</b><small>Registered</small></div>
                <div><b>{{ $counts['updated'] }}</b><small>Edits</small></div>
                <div><b>{{ $counts['photos'] }}</b><small>Photos</small></div>
            </div>
        </div>
    </section>

    <div class="ui-grid-3">
        <div style="display:flex;flex-direction:column;gap:18px;min-width:0">

            <section class="ui-card">
                <div class="ui-card-head"><span class="ico"><i class="bi bi-person"></i></span><div><h3>Profile</h3><p>Your name and sign-in email</p></div></div>
                <form method="POST" action="{{ route('account.profile') }}">
                    @csrf @method('PUT')
                    <div class="ui-form-grid">
                        <div class="ui-field">
                            <label for="name">Full name</label>
                            <input class="ui-input {{ $pb->has('name') ? 'is-invalid' : '' }}" id="name" name="name" value="{{ $pb->any() ? old('name') : $user->name }}" required>
                            @if ($pb->has('name'))<span class="ui-err">{{ $pb->first('name') }}</span>@endif
                        </div>
                        <div class="ui-field">
                            <label for="email">Email</label>
                            <input class="ui-input {{ $pb->has('email') ? 'is-invalid' : '' }}" id="email" type="email" name="email" value="{{ $pb->any() ? old('email') : $user->email }}" required>
                            @if ($pb->has('email'))<span class="ui-err">{{ $pb->first('email') }}</span>@endif
                        </div>
                    </div>
                    <div style="margin-top:16px;display:flex;justify-content:flex-end"><button class="ui-btn primary"><i class="bi bi-check2"></i> Save profile</button></div>
                </form>
            </section>

            <section class="ui-card">
                <div class="ui-card-head"><span class="ico"><i class="bi bi-shield-lock"></i></span><div><h3>Change password</h3><p>You'll stay signed in here; other devices are signed out.</p></div></div>
                <form method="POST" action="{{ route('account.password') }}">
                    @csrf @method('PUT')
                    <div class="ui-form-grid">
                        <div class="ui-field full">
                            <label for="current_password">Current password</label>
                            <input class="ui-input {{ $wb->has('current_password') ? 'is-invalid' : '' }}" id="current_password" type="password" name="current_password" autocomplete="current-password" required>
                            @if ($wb->has('current_password'))<span class="ui-err">{{ $wb->first('current_password') }}</span>@endif
                        </div>
                        <div class="ui-field">
                            <label for="password">New password</label>
                            <input class="ui-input {{ $wb->has('password') ? 'is-invalid' : '' }}" id="password" type="password" name="password" autocomplete="new-password" required>
                            <div class="pw-meter"><span id="pwMeter"></span></div>
                            <span class="ui-help" id="pwHint">At least 8 characters, with letters and numbers.</span>
                            @if ($wb->has('password'))<span class="ui-err">{{ $wb->first('password') }}</span>@endif
                        </div>
                        <div class="ui-field">
                            <label for="password_confirmation">Confirm new password</label>
                            <input class="ui-input" id="password_confirmation" type="password" name="password_confirmation" autocomplete="new-password" required>
                        </div>
                    </div>
                    <div style="margin-top:16px;display:flex;justify-content:flex-end"><button class="ui-btn primary"><i class="bi bi-key"></i> Change password</button></div>
                </form>
            </section>
        </div>

        <section class="ui-card">
            <div class="ui-card-head">
                <span class="ico"><i class="bi bi-clock-history"></i></span>
                <div><h3>Your recent activity</h3></div>
                <a class="end ui-link" style="font-size:13px" href="{{ route('activity.index', ['user' => $user->id]) }}">All</a>
            </div>
            <div class="mini-log">
                @forelse ($recent as $log)
                    @php [$icon, $fg, $bg] = $log->presentation(); @endphp
                    <div>
                        <i class="bi {{ $icon }}" style="background:{{ $bg }};color:{{ $fg }}"></i>
                        <span>{{ $log->description }}<small>{{ $log->created_at->diffForHumans() }}</small></span>
                    </div>
                @empty
                    <p style="margin:0;color:var(--muted);font-size:13px">No activity yet.</p>
                @endforelse
            </div>
        </section>
    </div>
</div>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    const pw = document.getElementById('password');
    const meter = document.getElementById('pwMeter');
    const hint = document.getElementById('pwHint');

    pw.addEventListener('input', function () {
        const v = this.value;
        let score = 0;
        if (v.length >= 8) score++;
        if (v.length >= 12) score++;
        if (/[a-z]/i.test(v) && /\d/.test(v)) score++;
        if (/[A-Z]/.test(v) && /[a-z]/.test(v)) score++;
        if (/[^A-Za-z0-9]/.test(v)) score++;

        const levels = [['0%', '#e2e8f0', 'At least 8 characters, with letters and numbers.'], ['25%', '#ef4444', 'Weak'], ['45%', '#f59e0b', 'Fair'], ['65%', '#eab308', 'Good'], ['85%', '#22c55e', 'Strong'], ['100%', '#15803d', 'Very strong']];
        const [w, c, t] = levels[v ? Math.max(score, 1) : 0];
        meter.style.width = w;
        meter.style.background = c;
        hint.textContent = t;
    });
});
</script>
@endpush
