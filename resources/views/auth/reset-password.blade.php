@extends('layouts.guest')

@section('title', 'Choose a new password')
@section('icon', 'bi-shield-lock')

@section('content')
    <h1>Choose a new password</h1>
    <p class="sub">Use at least 8 characters with upper and lower case letters, a number and a symbol. After saving, sign in with the new password.</p>

    <form method="POST" action="{{ route('password.update') }}">
        @csrf
        <input type="hidden" name="token" value="{{ $token }}">
        <div>
            <label for="email">Email address</label>
            <div class="control">
                <i class="bi bi-envelope"></i>
                <input type="email" id="email" name="email" value="{{ old('email', $email) }}" required autocomplete="email" class="{{ $errors->has('email') ? 'is-invalid' : '' }}">
            </div>
            @error('email')<span class="err">{{ $message }}</span>@enderror
        </div>
        <div>
            <label for="password">New password</label>
            <div class="control">
                <i class="bi bi-lock"></i>
                <input type="password" id="password" name="password" required minlength="8" autocomplete="new-password" autofocus class="{{ $errors->has('password') ? 'is-invalid' : '' }}">
            </div>
            @error('password')<span class="err">{{ $message }}</span>@enderror
        </div>
        <div>
            <label for="password_confirmation">Confirm new password</label>
            <div class="control">
                <i class="bi bi-lock-fill"></i>
                <input type="password" id="password_confirmation" name="password_confirmation" required minlength="8" autocomplete="new-password">
            </div>
        </div>
        <button class="btn"><i class="bi bi-check2-circle"></i> Save new password</button>
    </form>

    <p class="links"><a href="{{ route('login') }}"><i class="bi bi-arrow-left"></i> Back to sign in</a></p>
@endsection
