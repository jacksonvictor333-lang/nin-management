<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\User;
use App\Support\QrCode;
use App\Support\Totp;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Uthibitishaji wa hatua mbili (2FA) kwa programu ya authenticator.
 */
class TwoFactorController extends Controller
{
    // ---------------- Kuwasha / kuzima (akaunti yangu) ----------------

    /** Hatua ya 1: tengeneza siri mpya (bado haijathibitishwa). */
    public function enable(Request $request)
    {
        $user = $request->user();
        abort_if($user->hasTwoFactor(), 409);

        $user->forceFill([
            'two_factor_secret' => Totp::generateSecret(),
            'two_factor_confirmed_at' => null,
            'two_factor_recovery_codes' => null,
        ])->save();

        return redirect()->to(route('account.show') . '#two-factor');
    }

    /** Hatua ya 2: mtumiaji anaandika nambari kutoka kwenye programu. */
    public function confirm(Request $request)
    {
        $request->validateWithBag('twoFactor', ['code' => ['required', 'string']]);
        $user = $request->user();

        if (blank($user->two_factor_secret) || ! Totp::verify($user->two_factor_secret, $request->input('code'))) {
            return redirect()->to(route('account.show') . '#two-factor')
                ->withErrors(['code' => 'That code is not correct. Check the time on your phone and try the newest code.'], 'twoFactor');
        }

        $codes = $this->newRecoveryCodes();
        $user->forceFill(['two_factor_confirmed_at' => now(), 'two_factor_recovery_codes' => $codes])->save();

        ActivityLog::record('auth.2fa_enabled', "{$user->name} turned on two-factor authentication", $user);

        return redirect()->to(route('account.show') . '#two-factor')
            ->with('success', 'Two-factor authentication is on.')
            ->with('recovery_codes', $codes);
    }

    public function cancel(Request $request)
    {
        $user = $request->user();
        if (! $user->hasTwoFactor()) {
            $user->forceFill(['two_factor_secret' => null, 'two_factor_recovery_codes' => null, 'two_factor_confirmed_at' => null])->save();
        }

        return redirect()->to(route('account.show') . '#two-factor');
    }

    public function disable(Request $request)
    {
        $this->checkPassword($request);
        $user = $request->user();

        $user->forceFill(['two_factor_secret' => null, 'two_factor_recovery_codes' => null, 'two_factor_confirmed_at' => null])->save();
        ActivityLog::record('auth.2fa_disabled', "{$user->name} turned off two-factor authentication", $user);

        return redirect()->to(route('account.show') . '#two-factor')->with('success', 'Two-factor authentication is off.');
    }

    public function recoveryCodes(Request $request)
    {
        $this->checkPassword($request);
        $user = $request->user();
        abort_unless($user->hasTwoFactor(), 409);

        $codes = $this->newRecoveryCodes();
        $user->forceFill(['two_factor_recovery_codes' => $codes])->save();
        ActivityLog::record('auth.2fa_codes', "{$user->name} generated new recovery codes", $user);

        return redirect()->to(route('account.show') . '#two-factor')
            ->with('success', 'New recovery codes generated. The old ones no longer work.')
            ->with('recovery_codes', $codes);
    }

    /** Taarifa za kuweka 2FA (QR na siri) kwa ukurasa wa akaunti. */
    public static function setupFor(User $user): ?array
    {
        if (blank($user->two_factor_secret) || $user->hasTwoFactor()) {
            return null;
        }

        return [
            'secret' => trim(chunk_split($user->two_factor_secret, 4, ' ')),
            'qr' => QrCode::svg(Totp::uri($user->two_factor_secret, $user->email, config('app.name', 'NIN Management')), 200),
        ];
    }

    // ---------------- Wakati wa kuingia ----------------

    public function challenge(Request $request)
    {
        if (! $this->pendingUser($request)) {
            return redirect()->route('login');
        }

        return view('auth.two-factor-challenge');
    }

    public function verify(Request $request)
    {
        $user = $this->pendingUser($request);
        if (! $user) {
            return redirect()->route('login')->withErrors(['email' => 'Your sign-in session expired. Please sign in again.']);
        }

        $key = '2fa|' . $user->id . '|' . $request->ip();
        if (RateLimiter::tooManyAttempts($key, 5)) {
            throw ValidationException::withMessages(['code' => 'Too many attempts. Try again in ' . RateLimiter::availableIn($key) . ' seconds.']);
        }

        $ok = false;
        if ($request->filled('recovery_code')) {
            $ok = $this->useRecoveryCode($user, $request->input('recovery_code'));
        } elseif ($request->filled('code')) {
            $step = Totp::verify($user->two_factor_secret, $request->input('code'));
            // Nambari moja haitumiki mara mbili.
            $ok = $step !== null && Cache::add("2fa-used:{$user->id}:{$step}", true, 120);
        }

        if (! $ok) {
            RateLimiter::hit($key, 60);
            throw ValidationException::withMessages([
                $request->filled('recovery_code') ? 'recovery_code' : 'code' => 'That code is not correct.',
            ]);
        }

        RateLimiter::clear($key);
        $remember = (bool) $request->session()->pull('login.2fa.remember', false);
        $request->session()->forget(['login.2fa.id', 'login.2fa.expires']);

        Auth::login($user, $remember);
        $request->session()->regenerate();

        $user->forceFill(['last_login_at' => now()])->save();
        ActivityLog::record('auth.login', $user->name . ' signed in (two-factor)');

        return redirect()->intended(route('dashboard'));
    }

    private function pendingUser(Request $request): ?User
    {
        $id = $request->session()->get('login.2fa.id');
        $expires = $request->session()->get('login.2fa.expires');

        if (! $id || ! $expires || now()->timestamp > $expires) {
            return null;
        }

        $user = User::find($id);

        return $user && $user->is_active && $user->hasTwoFactor() ? $user : null;
    }

    private function useRecoveryCode(User $user, string $code): bool
    {
        $code = Str::lower(trim($code));
        $codes = $user->two_factor_recovery_codes ?? [];

        foreach ($codes as $i => $stored) {
            if (hash_equals(Str::lower($stored), $code)) {
                unset($codes[$i]);
                $user->forceFill(['two_factor_recovery_codes' => array_values($codes)])->save();
                ActivityLog::record('auth.2fa_recovery', "{$user->name} signed in with a recovery code (" . count($codes) . ' left)', $user);

                return true;
            }
        }

        return false;
    }

    private function newRecoveryCodes(): array
    {
        return collect(range(1, 8))->map(fn () => Str::lower(Str::random(5) . '-' . Str::random(5)))->all();
    }

    private function checkPassword(Request $request): void
    {
        $request->validateWithBag('twoFactor', ['current_password' => ['required', 'string']]);

        if (! Hash::check($request->input('current_password'), $request->user()->password)) {
            throw ValidationException::withMessages(['current_password' => 'The password is incorrect.'])->errorBag('twoFactor');
        }
    }
}
