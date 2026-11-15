<?php

namespace App\Http\Controllers;

use App\Models\Person;
use App\Models\PersonReview;
use Carbon\Carbon;

class DashboardController extends Controller
{
    public function index()
    {
        $total  = Person::count();
        $male   = Person::whereRaw('LOWER(gender) = ?', ['male'])->count();
        $female = Person::whereRaw('LOWER(gender) = ?', ['female'])->count();
        $active = Person::where('status', 'Active')->count();

        $thisMonth = Person::whereBetween('created_at', [now()->startOfMonth(), now()->endOfMonth()])->count();

        $lastMonth = Person::whereBetween('created_at', [
            now()->subMonthNoOverflow()->startOfMonth(),
            now()->subMonthNoOverflow()->endOfMonth(),
        ])->count();

        $today = Person::whereDate('created_at', today())->count();

        // Umri wa kila mtu (miaka), kwa wastani na makundi ya umri.
        $ages = Person::whereNotNull('date_of_birth')
            ->pluck('date_of_birth')
            ->map(fn ($dob) => Carbon::parse($dob)->age);

        $avgAge = $ages->isNotEmpty() ? (int) round($ages->avg()) : 0;

        $ageGroups = collect([
            '0–17' => [0, 17],
            '18–25' => [18, 25],
            '26–35' => [26, 35],
            '36–50' => [36, 50],
            '51–64' => [51, 64],
            '65+' => [65, 200],
        ])->map(fn ($range) => $ages->filter(fn ($a) => $a >= $range[0] && $a <= $range[1])->count());

        // Usajili kwa miezi 12 iliyopita (pamoja na mwezi huu).
        $start = now()->subMonthsNoOverflow(11)->startOfMonth();

        $byMonth = Person::where('created_at', '>=', $start)
            ->pluck('created_at')
            ->countBy(fn ($date) => Carbon::parse($date)->format('Y-m'));

        $monthly = collect(range(0, 11))->map(function ($i) use ($start, $byMonth) {
            $month = $start->copy()->addMonthsNoOverflow($i);

            return [
                'label' => $month->format('M'),
                'full' => $month->format('F Y'),
                'count' => $byMonth[$month->format('Y-m')] ?? 0,
            ];
        });

        // Mikoa 6 yenye watu wengi (makazi).
        $topRegions = Person::selectRaw("COALESCE(NULLIF(region, ''), 'Not specified') as name, COUNT(*) as total")
            ->groupBy('name')
            ->orderByDesc('total')
            ->limit(6)
            ->pluck('total', 'name');

        $recent = Person::latest()->take(8)->get();

        // Idhini: kwa anayeidhinisha (foleni nzima) na kwa Registrar (rekodi zake).
        $user = auth()->user();
        $approval = null;
        if ($user->canApprove() || $user->canRegister()) {
            $scope = fn () => Person::anyStage()->when(! $user->canApprove(),
                fn ($q) => $q->where(fn ($w) => $w->where('created_by', $user->id)->orWhere('submitted_by', $user->id)));

            $decided = PersonReview::where('action', 'approved')->where('created_at', '>=', now()->subDays(30))->pluck('person_id');
            $avgHours = $decided->isEmpty() ? null : Person::anyStage()->whereIn('id', $decided)->whereNotNull('submitted_at')->whereNotNull('reviewed_at')
                ->get(['submitted_at', 'reviewed_at'])
                ->avg(fn ($p) => $p->submitted_at->diffInMinutes($p->reviewed_at) / 60);

            $approval = [
                'mine' => ! $user->canApprove(),
                'pending' => $scope()->whereIn('stage', [Person::SUBMITTED, Person::IN_REVIEW])->count(),
                'approvedToday' => $scope()->where('stage', Person::APPROVED)->whereDate('reviewed_at', today())->count(),
                'returned' => $scope()->where('stage', Person::RETURNED)->count(),
                'rejected' => $scope()->where('stage', Person::REJECTED)->count(),
                'drafts' => $scope()->where('stage', Person::DRAFT)->count(),
                'avgHours' => $avgHours,
            ];
        }

        return view('dashboard', compact(
            'total', 'male', 'female', 'active',
            'thisMonth', 'lastMonth', 'today',
            'avgAge', 'ageGroups', 'monthly', 'topRegions', 'recent', 'approval'
        ));
    }
}
