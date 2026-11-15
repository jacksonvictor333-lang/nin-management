<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\User;
use Illuminate\Http\Request;

class ActivityController extends Controller
{
    public const GROUPS = [
        'person' => 'Persons',
        'photo' => 'Photos',
        'user' => 'Users',
        'auth' => 'Sign in / out',
        'data' => 'Data fixes',
        'report' => 'Reports',
        'location' => 'Locations',
        'api' => 'API',
    ];

    public function index(Request $request)
    {
        $group = array_key_exists($request->query('group'), self::GROUPS) ? $request->query('group') : null;
        $userId = $request->integer('user') ?: null;
        $search = trim((string) $request->query('q', ''));

        $logs = ActivityLog::with('user')
            ->when($group, fn ($q, $g) => $q->where('action', 'like', $g . '.%'))
            ->when($userId, fn ($q, $u) => $q->where('user_id', $u))
            ->when($search !== '', fn ($q) => $q->where('description', 'like', "%{$search}%"))
            ->latest('id')
            ->paginate(25)
            ->withQueryString();

        // Panga kwa siku: "Today", "Yesterday", "12 Nov 2026"
        $byDay = $logs->getCollection()->groupBy(function ($log) {
            $d = $log->created_at;

            return $d->isToday() ? 'Today' : ($d->isYesterday() ? 'Yesterday' : $d->format('l, d M Y'));
        });

        $users = User::orderBy('name')->get(['id', 'name']);

        return view('activity.index', [
            'logs' => $logs,
            'byDay' => $byDay,
            'users' => $users,
            'groups' => self::GROUPS,
            'group' => $group,
            'userId' => $userId,
            'search' => $search,
        ]);
    }
}
