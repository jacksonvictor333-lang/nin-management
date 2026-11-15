<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class UserController extends Controller
{
    public const ROLES = ['Administrator', 'Registrar', 'Viewer'];

    public function index()
    {
        $users = User::orderByDesc('is_active')->orderBy('name')->get();

        $lastActions = ActivityLog::selectRaw('user_id, MAX(created_at) as last_action')
            ->whereIn('user_id', $users->pluck('id'))
            ->groupBy('user_id')
            ->pluck('last_action', 'user_id');

        return view('users.index', ['users' => $users, 'roles' => self::ROLES, 'lastActions' => $lastActions]);
    }

    public function store(Request $request)
    {
        $data = $request->validateWithBag('create', [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'role' => ['required', Rule::in(self::ROLES)],
            'password' => ['required', 'confirmed', Password::min(8)->letters()->numbers()],
        ]);

        $user = User::create($data + ['is_active' => true]);

        ActivityLog::record('user.created', "Created user {$user->name} ({$user->email}) as {$user->role}", $user);

        return back()->with('success', "User {$user->name} created.");
    }

    public function update(Request $request, User $user)
    {
        $data = $request->validateWithBag('edit' . $user->id, [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
            'role' => ['required', Rule::in(self::ROLES)],
            'password' => ['nullable', 'confirmed', Password::min(8)->letters()->numbers()],
        ]);

        if (blank($data['password'] ?? null)) {
            unset($data['password']);
        }

        // Usijiondolee cheo cha Administrator mwenyewe (usije ukajifungia nje).
        if ($user->is(auth()->user()) && $data['role'] !== 'Administrator') {
            return back()->withErrors(['role' => 'You cannot remove your own Administrator role.'], 'edit' . $user->id);
        }

        $user->fill($data);
        $changed = array_keys($user->getDirty());
        $user->save();

        if ($changed) {
            $fields = array_map(fn ($f) => $f === 'password' ? 'password (reset)' : $f, $changed);
            ActivityLog::record('user.updated', "Updated user {$user->name}: " . implode(', ', $fields), $user);
        }

        return back()->with('success', "User {$user->name} updated.");
    }

    public function toggle(User $user)
    {
        if ($user->is(auth()->user())) {
            return back()->with('error', 'You cannot deactivate your own account.');
        }

        $user->update(['is_active' => ! $user->is_active]);

        ActivityLog::record('user.' . ($user->is_active ? 'activated' : 'deactivated'), ($user->is_active ? 'Activated' : 'Deactivated') . " user {$user->name}", $user);

        return back()->with('success', "{$user->name} has been " . ($user->is_active ? 'activated.' : 'deactivated and can no longer sign in.'));
    }

    public function destroy(User $user)
    {
        if ($user->is(auth()->user())) {
            return back()->with('error', 'You cannot delete your own account.');
        }

        $name = $user->name;
        $email = $user->email;
        $user->delete();

        ActivityLog::record('user.deleted', "Deleted user {$name} ({$email})");

        return back()->with('success', "User {$name} deleted.");
    }
}
