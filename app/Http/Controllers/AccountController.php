<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class AccountController extends Controller
{
    public function show(Request $request)
    {
        $user = $request->user();

        $recent = ActivityLog::where('user_id', $user->id)->latest('id')->take(8)->get();

        $counts = [
            'registered' => ActivityLog::where('user_id', $user->id)->where('action', 'person.created')->count(),
            'updated' => ActivityLog::where('user_id', $user->id)->where('action', 'person.updated')->count(),
            'photos' => ActivityLog::where('user_id', $user->id)->where('action', 'like', 'photo.%')->count(),
        ];

        return view('account.show', compact('user', 'recent', 'counts'));
    }

    public function updateProfile(Request $request)
    {
        $user = $request->user();

        $data = $request->validateWithBag('profile', [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
        ]);

        $user->fill($data);
        $changed = array_keys($user->getDirty());
        $user->save();

        if ($changed) {
            ActivityLog::record('user.profile_updated', "{$user->name} updated their profile: " . implode(', ', $changed), $user);
        }

        return back()->with('success', 'Profile updated.');
    }

    public function updatePassword(Request $request)
    {
        $request->validateWithBag('password', [
            'current_password' => ['required', 'current_password'],
            'password' => ['required', 'confirmed', 'different:current_password', Password::min(8)->letters()->numbers()],
        ]);

        $user = $request->user();
        $user->update(['password' => $request->input('password')]);

        // Toa session nyingine zote za mtumiaji huyu (vifaa vingine).
        Auth::logoutOtherDevices($request->input('password'));

        ActivityLog::record('user.password_changed', "{$user->name} changed their password", $user);

        return back()->with('success', 'Password changed. Other devices have been signed out.');
    }
}
