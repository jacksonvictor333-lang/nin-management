<?php

namespace App\Http\Controllers;

use App\Models\Person;
use App\Models\PersonReview;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/**
 * Utendaji wa watumiaji kwa kipindi: Registrar (amesajili wangapi, wangapi
 * wamerudishwa) na Approver (maamuzi, muda wa wastani wa kuidhinisha).
 */
class PerformanceController extends Controller
{
    public const PERIODS = [7 => 'Last 7 days', 30 => 'Last 30 days', 90 => 'Last 90 days', 365 => 'Last 12 months'];

    public function index(Request $request)
    {
        $days = (int) $request->query('days', 30);
        $days = array_key_exists($days, self::PERIODS) ? $days : 30;
        $from = Carbon::now()->subDays($days)->startOfDay();

        $users = User::orderBy('name')->get(['id', 'name', 'role', 'is_active'])->keyBy('id');

        // Hatua zote za idhini katika kipindi.
        $events = PersonReview::where('created_at', '>=', $from)->get(['person_id', 'user_id', 'action', 'created_at']);

        // ---- Registrar ----
        $registered = Person::anyStage()->withTrashed()->where('created_at', '>=', $from)
            ->whereNotNull('created_by')->selectRaw('created_by, COUNT(*) as c')->groupBy('created_by')->pluck('c', 'created_by');

        $submittersOf = Person::anyStage()->withTrashed()->whereIn('id', $events->pluck('person_id')->unique())
            ->pluck('created_by', 'id');

        $registrars = [];
        foreach ($users as $id => $u) {
            $mine = $events->filter(fn ($e) => ($submittersOf[$e->person_id] ?? null) === $id);
            $submitted = $events->where('user_id', $id)->whereIn('action', ['submitted', 'resubmitted'])->count();
            $returned = $mine->where('action', 'returned')->count();
            $approved = $mine->where('action', 'approved')->count();
            $rejected = $mine->where('action', 'rejected')->count();
            $decided = $approved + $returned + $rejected;

            if (($registered[$id] ?? 0) || $submitted || $decided) {
                $registrars[] = [
                    'user' => $u,
                    'registered' => (int) ($registered[$id] ?? 0),
                    'submitted' => $submitted,
                    'approved' => $approved,
                    'returned' => $returned,
                    'rejected' => $rejected,
                    'quality' => $decided ? (int) round($approved / $decided * 100) : null,
                ];
            }
        }
        usort($registrars, fn ($a, $b) => $b['registered'] <=> $a['registered']);

        // ---- Approver ----
        $approvers = [];
        $decisions = $events->whereIn('action', ['approved', 'returned', 'rejected']);
        foreach ($decisions->groupBy('user_id') as $id => $rows) {
            if (! $users->has($id)) {
                continue;
            }

            // Muda kutoka kuwasilishwa hadi uamuzi (kwa kila uamuzi).
            $hours = $rows->map(function ($d) {
                $submitted = PersonReview::where('person_id', $d->person_id)
                    ->whereIn('action', ['submitted', 'resubmitted', 'edited'])
                    ->where('created_at', '<=', $d->created_at)
                    ->latest('id')->value('created_at');

                return $submitted ? Carbon::parse($submitted)->diffInMinutes($d->created_at) / 60 : null;
            })->filter(fn ($h) => $h !== null);

            $approvers[] = [
                'user' => $users[$id],
                'decisions' => $rows->count(),
                'approved' => $rows->where('action', 'approved')->count(),
                'returned' => $rows->where('action', 'returned')->count(),
                'rejected' => $rows->where('action', 'rejected')->count(),
                'avgHours' => $hours->isEmpty() ? null : $hours->avg(),
            ];
        }
        usort($approvers, fn ($a, $b) => $b['decisions'] <=> $a['decisions']);

        $totals = [
            'registered' => array_sum(array_column($registrars, 'registered')),
            'approved' => $decisions->where('action', 'approved')->count(),
            'returned' => $decisions->where('action', 'returned')->count(),
            'rejected' => $decisions->where('action', 'rejected')->count(),
            'waiting' => Person::anyStage()->whereIn('stage', [Person::SUBMITTED, Person::IN_REVIEW])->count(),
        ];

        return view('reports.performance', [
            'days' => $days,
            'periods' => self::PERIODS,
            'registrars' => $registrars,
            'approvers' => $approvers,
            'totals' => $totals,
        ]);
    }

    public static function hours(?float $h): string
    {
        return match (true) {
            $h === null => '—',
            $h < 1 => round($h * 60) . ' min',
            $h < 48 => round($h, 1) . ' h',
            default => round($h / 24, 1) . ' days',
        };
    }
}
