<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\Person;
use Illuminate\Http\Request;

/**
 * Archive: rekodi hazifutwi kabisa (soft delete). Zinaweza kurudishwa.
 * Rekodi iliyohifadhiwa haionekani kwenye orodha, utafutaji, API ya uthibitishaji
 * wala QR ya kadi hadi irudishwe.
 */
class ArchiveController extends Controller
{
    public function index(Request $request)
    {
        $search = trim((string) $request->query('q', ''));

        $people = Person::anyStage()->onlyTrashed()
            ->when($search !== '', fn ($q) => $q->where(fn ($w) => $w
                ->where('nin', 'like', "%{$search}%")
                ->orWhere('first_name', 'like', "%{$search}%")
                ->orWhere('last_name', 'like', "%{$search}%")))
            ->latest('deleted_at')
            ->paginate(15)
            ->withQueryString();

        return view('archive.index', compact('people', 'search'));
    }

    public function destroy(Request $request, Person $person)
    {
        $data = $request->validate([
            'archive_reason' => ['required', 'string', 'min:3', 'max:255'],
        ]);

        $person->update(['archive_reason' => $data['archive_reason']]);
        $person->delete();

        ActivityLog::record('person.archived', "Archived {$person->full_name} (NIN {$person->nin}): {$data['archive_reason']}", $person);

        return redirect()
            ->route('people.index')
            ->with('success', "{$person->full_name} was moved to the archive. It can be restored from Archive.");
    }

    public function restore(int $id)
    {
        $person = Person::anyStage()->onlyTrashed()->findOrFail($id);
        $person->restore();
        $person->update(['archive_reason' => null]);

        ActivityLog::record('person.restored', "Restored {$person->full_name} (NIN {$person->nin}) from the archive", $person);

        return redirect()
            ->route('people.show', $person)
            ->with('success', "{$person->full_name} was restored.");
    }
}
