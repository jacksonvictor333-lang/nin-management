<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    /**
     * Onyesha fomu ya kuingia.
     */
    public function showLogin()
    {
        return view('auth.login');
    }

    /**
     * Thibitisha mtumiaji na anzisha session.
     */
    public function login(Request $request)
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        // Zuia kubahatisha nywila: majaribio 5 kwa dakika kwa email + IP.
        $throttleKey = Str::lower($credentials['email']) . '|' . $request->ip();

        if (RateLimiter::tooManyAttempts($throttleKey, 5)) {
            $seconds = RateLimiter::availableIn($throttleKey);

            throw ValidationException::withMessages([
                'email' => "Too many login attempts. Try again in {$seconds} seconds.",
            ]);
        }

        if (! Auth::attempt($credentials, $request->boolean('remember'))) {
            RateLimiter::hit($throttleKey, 60);

            throw ValidationException::withMessages([
                'email' => 'These credentials do not match our records.',
            ]);
        }

        // Akaunti iliyozimwa haiwezi kuingia.
        if (! Auth::user()->is_active) {
            Auth::logout();

            throw ValidationException::withMessages([
                'email' => 'This account has been deactivated. Contact an administrator.',
            ]);
        }

        RateLimiter::clear($throttleKey);

        // 2FA: nywila ni sahihi, lakini bado lazima aingize nambari ya authenticator.
        if (Auth::user()->hasTwoFactor()) {
            $user = Auth::user();
            Auth::logout();
            $request->session()->regenerate();
            $request->session()->put([
                'login.2fa.id' => $user->id,
                'login.2fa.remember' => $request->boolean('remember'),
                'login.2fa.expires' => now()->addMinutes(5)->timestamp,
            ]);

            return redirect()->route('two-factor.challenge');
        }

        $request->session()->regenerate();

        Auth::user()->forceFill(['last_login_at' => now()])->save();
        ActivityLog::record('auth.login', Auth::user()->name . ' signed in');

        return redirect()->intended(route('dashboard'));
    }

    /**
     * Toka kwenye mfumo.
     */
    public function logout(Request $request)
    {
        if (Auth::check()) {
            ActivityLog::record('auth.logout', Auth::user()->name . ' signed out');
        }

        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}
