@extends('layouts.guest')

@section('title', 'Two-factor authentication')
@section('icon', 'bi-shield-lock')

@section('content')
    @php $useRecovery = $errors->has('recovery_code'); @endphp

    <h1>Two-step verification</h1>
    <p class="sub" id="hintCode" @if ($useRecovery) hidden @endif>Open your authenticator app and enter the 6-digit code for <b>{{ config('app.name') }}</b>.</p>
    <p class="sub" id="hintRecovery" @unless ($useRecovery) hidden @endunless>Enter one of the recovery codes you saved when you turned on two-step verification. Each code works once.</p>

    <form method="POST" action="{{ route('two-factor.verify') }}" id="tfForm">
        @csrf
        <div id="boxCode" @if ($useRecovery) hidden @endif>
            <label for="code">Authentication code</label>
            <input type="text" id="code" name="code" class="code {{ $errors->has('code') ? 'is-invalid' : '' }}" inputmode="numeric" autocomplete="one-time-code" maxlength="7" pattern="[0-9 ]*" placeholder="000000" @unless ($useRecovery) autofocus @endunless>
            @error('code')<span class="err">{{ $message }}</span>@enderror
        </div>
        <div id="boxRecovery" @unless ($useRecovery) hidden @endunless>
            <label for="recovery_code">Recovery code</label>
            <div class="control">
                <i class="bi bi-key"></i>
                <input type="text" id="recovery_code" name="recovery_code" class="{{ $errors->has('recovery_code') ? 'is-invalid' : '' }}" autocomplete="off" placeholder="abcde-12345">
            </div>
            @error('recovery_code')<span class="err">{{ $message }}</span>@enderror
        </div>
        <button class="btn"><i class="bi bi-box-arrow-in-right"></i> Verify and sign in</button>
    </form>

    <p class="links">
        <button type="button" class="toggle" id="toggle">{{ $useRecovery ? 'Use an authentication code' : 'Lost your phone? Use a recovery code' }}</button>
        <br><br><a href="{{ route('login') }}"><i class="bi bi-arrow-left"></i> Back to sign in</a>
    </p>

    <script>
        (function () {
            const code = document.getElementById('code'), rec = document.getElementById('recovery_code');
            const show = (recovery) => {
                document.getElementById('boxCode').hidden = recovery;
                document.getElementById('hintCode').hidden = recovery;
                document.getElementById('boxRecovery').hidden = !recovery;
                document.getElementById('hintRecovery').hidden = !recovery;
                document.getElementById('toggle').textContent = recovery ? 'Use an authentication code' : 'Lost your phone? Use a recovery code';
                (recovery ? code : rec).value = '';
                (recovery ? rec : code).focus();
            };
            document.getElementById('toggle').addEventListener('click', () => show(document.getElementById('boxRecovery').hidden));
            // Tarakimu 6 zikikamilika, tuma moja kwa moja.
            code.addEventListener('input', () => {
                if (code.value.replace(/\D/g, '').length === 6) document.getElementById('tfForm').submit();
            });
        })();
    </script>
@endsection
