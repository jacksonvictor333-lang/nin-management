@extends('layouts.guest')

@section('title', 'Forgot password')
@section('icon', 'bi-key')

@section('content')
    <h1>Forgot your password?</h1>
    <p class="sub">Enter the email of your staff account and we will send you a link to choose a new password.</p>

    @if (session('status'))
        <div class="alert ok"><i class="bi bi-envelope-check"></i><span>{{ session('status') }}</span></div>
    @endif

    <form method="POST" action="{{ route('password.email') }}" onsubmit="this.querySelector('button').disabled = true">
        @csrf
        <div>
            <label for="email">Email address</label>
            <div class="control">
                <i class="bi bi-envelope"></i>
                <input type="email" id="email" name="email" value="{{ old('email') }}" required autofocus autocomplete="email" class="{{ $errors->has('email') ? 'is-invalid' : '' }}" placeholder="you@example.com">
            </div>
            @error('email')<span class="err">{{ $message }}</span>@enderror
        </div>
        <button class="btn"><i class="bi bi-send"></i> Send reset link</button>
    </form>

    <p class="links"><a href="{{ route('login') }}"><i class="bi bi-arrow-left"></i> Back to sign in</a></p>
@endsection
