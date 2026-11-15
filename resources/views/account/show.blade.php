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
    .tf-text { margin: 0 0 12px; font-size: 13px; color: var(--muted); }
    .tf-pass { display: flex; gap: 10px; align-items: flex-end; flex-wrap: wrap; }
    .tf-setup { display: flex; gap: 18px; align-items: flex-start; flex-wrap: wrap; }
    .tf-setup .qr { width: 168px; height: 168px; padding: 8px; border: 1px solid var(--border); border-radius: 14px; background: #fff; flex-shrink: 0; }
    .tf-setup .qr svg { width: 100%; height: 100%; display: block; }
    .tf-setup ol { margin: 0 0 12px; padding-left: 18px; font-size: 13px; color: var(--text); line-height: 1.7; }
    .tf-setup small { color: var(--muted); }
    .tf-code { letter-spacing: 6px; font-size: 18px; font-weight: 700; text-align: center; font-family: ui-monospace, Consolas, monospace; }
    .tf-codes { margin-bottom: 14px; padding: 14px; border-radius: 12px; background: #fffbeb; border: 1px solid #fde68a; }
    .tf-codes b { color: #92400e; font-size: 14px; }
    .tf-codes p { margin: 4px 0 10px; font-size: 12.5px; color: #92400e; }
    .tf-codes .grid { display: grid; grid-template-columns: repeat(2, 1fr); gap: 6px; margin-bottom: 10px; }
    .tf-codes code { font-family: ui-monospace, Consolas, monospace; font-size: 13px; padding: 6px 10px; background: #fff; border-radius: 8px; border: 1px dashed #fcd34d; text-align: center; }
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
            @php
                $tb = $errors->getBag('twoFactor');
                $setup = \App\Http\Controllers\TwoFactorController::setupFor($user);
                $codes = session('recovery_codes');
            @endphp
            <section class="ui-card" id="two-factor">
                <div class="ui-card-head">
                    <span class="ico"><i class="bi bi-phone-vibrate"></i></span>
                    <div><h3>Two-step verification</h3><p>A code from your phone is needed at every sign-in, so a stolen password is not enough.</p></div>
                    @if ($user->hasTwoFactor())
                        <span class="end ui-pill ok"><i class="bi bi-shield-check"></i> On</span>
                    @else
                        <span class="end ui-pill {{ $user->role === 'Administrator' ? 'warn' : '' }}">Off</span>
                    @endif
                </div>

                @if ($codes)
                    <div class="tf-codes">
                        <b><i class="bi bi-key"></i> Save these recovery codes now</b>
                        <p>Each code lets you sign in once if you lose your phone. They will not be shown again.</p>
                        <div class="grid">@foreach ($codes as $c)<code>{{ $c }}</code>@endforeach</div>
                        <button type="button" class="ui-btn sm" id="copyCodes" data-codes="{{ implode("\n", $codes) }}"><i class="bi bi-copy"></i> Copy codes</button>
                    </div>
                    <script>
                        document.getElementById('copyCodes').addEventListener('click', function () {
                            navigator.clipboard.writeText(this.dataset.codes).then(() => { this.textContent = 'Copied'; });
                        });
                    </script>
                @endif

                @if ($user->hasTwoFactor())
                    <p class="tf-text">Turned on {{ $user->two_factor_confirmed_at->diffForHumans() }}. {{ count($user->two_factor_recovery_codes ?? []) }} recovery {{ Str::plural('code', count($user->two_factor_recovery_codes ?? [])) }} left.</p>
                    <form method="POST" class="tf-pass" action="{{ route('two-factor.recovery-codes') }}">
                        @csrf
                        <div class="ui-field" style="flex:1;min-width:200px">
                            <label for="tf_password">Confirm with your password</label>
                            <input class="ui-input {{ $tb->has('current_password') ? 'is-invalid' : '' }}" id="tf_password" type="password" name="current_password" autocomplete="current-password" required>
                            @if ($tb->has('current_password'))<span class="ui-err">{{ $tb->first('current_password') }}</span>@endif
                        </div>
                        <button class="ui-btn"><i class="bi bi-arrow-repeat"></i> New recovery codes</button>
                        <button class="ui-btn danger" formaction="{{ route('two-factor.disable') }}" name="_method" value="DELETE" onclick="return confirm('Turn off two-step verification?')"><i class="bi bi-shield-x"></i> Turn off</button>
                    </form>
                @elseif ($setup)
                    <div class="tf-setup">
                        <div class="qr">{!! $setup['qr'] !!}</div>
                        <div style="flex:1;min-width:220px">
                            <ol>
                                <li>Install <b>Google Authenticator</b>, <b>Microsoft Authenticator</b> or <b>Authy</b> on your phone.</li>
                                <li>In the app, add an account and scan this QR code.<br><small>Can't scan? Enter this key: <span class="ui-mono">{{ $setup['secret'] }}</span></small></li>
                                <li>Type the 6-digit code the app shows:</li>
                            </ol>
                            <form method="POST" action="{{ route('two-factor.confirm') }}" class="tf-pass">
                                @csrf
                                <div class="ui-field" style="flex:1;min-width:140px">
                                    <input class="ui-input tf-code {{ $tb->has('code') ? 'is-invalid' : '' }}" name="code" inputmode="numeric" autocomplete="one-time-code" maxlength="7" placeholder="000000" required autofocus>
                                    @if ($tb->has('code'))<span class="ui-err">{{ $tb->first('code') }}</span>@endif
                                </div>
                                <button class="ui-btn primary"><i class="bi bi-check2-circle"></i> Confirm</button>
                            </form>
                            <form method="POST" action="{{ route('two-factor.cancel') }}" style="margin-top:8px">@csrf<button class="ui-link" style="background:none;border:0;cursor:pointer;font-size:13px;padding:0">Cancel</button></form>
                        </div>
                    </div>
                @else
                    @if ($user->role === 'Administrator')
                        <p class="tf-text" style="color:#92400e"><i class="bi bi-exclamation-triangle"></i> Strongly recommended for administrator accounts.</p>
                    @endif
                    <form method="POST" action="{{ route('two-factor.enable') }}">
                        @csrf
                        <button class="ui-btn primary"><i class="bi bi-shield-lock"></i> Turn on two-step verification</button>
                    </form>
                @endif
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
