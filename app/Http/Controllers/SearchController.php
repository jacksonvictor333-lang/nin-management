<?php

namespace App\Http\Controllers;

use App\Models\Person;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;

class SearchController extends Controller
{
    /**
     * Ukurasa wa kutafuta: NIN kamili inaleta mtu mmoja moja kwa moja;
     * maandishi mengine yanatafuta kwa jina, simu, au sehemu ya NIN.
     */
    public function index(Request $request)
    {
        $q = trim((string) $request->query('q', ''));
        [$exact, $results] = $q !== '' ? $this->search($q, 30) : [null, collect()];

        return view('search.index', compact('q', 'exact', 'results'));
    }

    /**
     * JSON kwa "Quick lookup" ya dashboard: jina au NIN, majibu machache.
     */
    public function quick(Request $request): JsonResponse
    {
        $q = trim((string) $request->query('q', ''));

        if (mb_strlen($q) < 2) {
            return response()->json(['results' => [], 'total' => 0]);
        }

        [$exact, $results] = $this->search($q, 7);
        $people = $exact ? collect([$exact]) : $results;

        return response()->json([
            'exact' => (bool) $exact,
            'total' => $people->count(),
            'more_url' => route('search', ['q' => $q]),
            'results' => $people->take(6)->map(fn (Person $p) => [
                'name' => $p->full_name,
                'nin' => $p->nin,
                'gender' => $p->gender,
                'age' => $p->date_of_birth?->age,
                'region' => $p->region,
                'status' => $p->status,
                'initials' => mb_strtoupper(mb_substr($p->first_name, 0, 1) . mb_substr($p->last_name, 0, 1)),
                'photo' => $p->profile_picture && Storage::disk('public')->exists($p->profile_picture)
                    ? asset('storage/' . $p->profile_picture)
                    : null,
                'url' => route('people.show', $p),
            ])->values(),
        ]);
    }

    /**
     * @return array{0: ?Person, 1: Collection}
     */
    private function search(string $q, int $limit): array
    {
        $digits = preg_replace('/\D/', '', $q);

        // NIN kamili (hata ikiwa na nafasi au vistari).
        if (strlen($digits) === 20 && ! preg_match('/[a-z]/i', $q)) {
            $exact = Person::where('nin', $digits)->first();

            if ($exact) {
                return [$exact, collect()];
            }
        }

        $terms = array_filter(preg_split('/\s+/', $q));

        $results = Person::query()
            ->where(function ($query) use ($digits, $terms, $q) {
                if ($digits !== '' && strlen($digits) >= 4 && ! preg_match('/[a-z]/i', $q)) {
                    $query->orWhere('nin', 'like', "%{$digits}%")
                        ->orWhere('phone', 'like', "%{$digits}%");
                }

                // Kila neno lilingane na sehemu yoyote ya jina.
                $query->orWhere(function ($names) use ($terms) {
                    foreach ($terms as $term) {
                        $names->where(function ($n) use ($term) {
                            $n->where('first_name', 'like', "%{$term}%")
                                ->orWhere('middle_name', 'like', "%{$term}%")
                                ->orWhere('last_name', 'like', "%{$term}%");
                        });
                    }
                });
            })
            // Majina yanayoanza na neno la kwanza yaje juu.
            ->orderByRaw('CASE WHEN first_name LIKE ? OR last_name LIKE ? THEN 0 ELSE 1 END', [reset($terms) . '%', reset($terms) . '%'])
            ->orderBy('first_name')
            ->limit($limit)
            ->get();

        return [null, $results];
    }
}
