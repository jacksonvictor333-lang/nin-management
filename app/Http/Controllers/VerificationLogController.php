<?php

namespace App\Http\Controllers;

use App\Models\NinVerification;
use App\Models\Person;
use Illuminate\Http\Request;

class VerificationLogController extends Controller
{
    private const MAX_FAILURES = 3;
    private const LOCKOUT_MINUTES = 60;

    public function index(Request $request)
    {
        $status = $request->query('status');
        $nin = preg_replace('/\D/', '', (string) $request->query('nin', ''));

        $verifications = NinVerification::query()
            ->when(in_array($status, ['passed', 'failed', 'pending'], true), fn ($q) => $q->where('status', $status))
            ->when($status === 'expired', fn ($q) => $q->where('status', 'pending')->where('expires_at', '<', now()))
            ->when($nin !== '', fn ($q) => $q->where('nin', 'like', "%{$nin}%"))
            ->latest('id')
            ->paginate(15)
            ->withQueryString();

        $people = Person::whereIn('nin', $verifications->pluck('nin')->unique())->get()->keyBy('nin');

        $since = now()->subDays(30);

        $stats = [
            'total' => NinVerification::where('created_at', '>=', $since)->count(),
            'passed' => NinVerification::where('created_at', '>=', $since)->where('status', 'passed')->count(),
            'failed' => NinVerification::where('created_at', '>=', $since)->where('status', 'failed')->count(),
            'pending' => NinVerification::where('status', 'pending')->where('expires_at', '>=', now())->count(),
        ];
        $stats['rate'] = ($stats['passed'] + $stats['failed']) > 0
            ? (int) round($stats['passed'] / ($stats['passed'] + $stats['failed']) * 100)
            : null;

        // NIN zilizofungiwa kwa sasa (kanuni ile ile ya API).
        $locked = NinVerification::selectRaw('nin, COUNT(*) as failures, MAX(updated_at) as last_failure')
            ->where('status', 'failed')
            ->where('updated_at', '>=', now()->subMinutes(self::LOCKOUT_MINUTES))
            ->groupBy('nin')
            ->having('failures', '>=', self::MAX_FAILURES)
            ->get();

        $lockedPeople = Person::whereIn('nin', $locked->pluck('nin'))->get()->keyBy('nin');

        return view('verifications.index', compact(
            'verifications', 'people', 'stats', 'locked', 'lockedPeople', 'status', 'nin'
        ));
    }
}
