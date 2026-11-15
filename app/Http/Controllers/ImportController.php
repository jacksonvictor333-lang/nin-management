<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\Person;
use App\Models\PersonReview;
use App\Support\PeopleImporter;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Kuingiza watu wengi kwa CSV: pakia -> kagua kila safu -> thibitisha -> hifadhi.
 */
class ImportController extends Controller
{
    public function index()
    {
        return view('import.index', ['columns' => PeopleImporter::COLUMNS, 'required' => PeopleImporter::REQUIRED]);
    }

    public function template()
    {
        $example = [
            'Amina', 'Juma', 'Mrisho', '1995-04-12', 'Female', 'Tanzanian',
            'Dodoma', 'Kondoa',
            'Dar es Salaam', 'Ilala', '', '',
            '0754123456', 'amina@example.com',
            'Juma Ally Mrisho', '0713000000', 'Rehema Said', '0715000000', '', '',
            'Diploma', 'Kisutu Primary School', '2008', 'Azania Secondary School', '2012',
            'Dar es Salaam Institute of Technology', 'Ordinary Diploma in Civil Engineering', 'Diploma',
        ];

        $csv = "\xEF\xBB\xBF" . implode(',', PeopleImporter::COLUMNS) . "\r\n"
            . implode(',', array_map(fn ($v) => str_contains($v, ',') ? '"' . $v . '"' : $v, $example)) . "\r\n";

        return response($csv, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="people-import-template.csv"',
        ]);
    }

    public function preview(Request $request, PeopleImporter $importer)
    {
        $request->validate([
            'file' => ['required', 'file', 'mimes:csv,txt', 'max:2048'],
        ], [
            'file.mimes' => 'Upload a CSV file. In Excel use File → Save As → "CSV UTF-8".',
        ]);

        try {
            $rows = $importer->parse($request->file('file')->getRealPath());
        } catch (RuntimeException $e) {
            return back()->withErrors(['file' => $e->getMessage()]);
        }

        $token = Str::random(32);
        Cache::put($this->key($token), $rows, now()->addMinutes(30));

        return view('import.preview', [
            'rows' => $rows,
            'token' => $token,
            'filename' => $request->file('file')->getClientOriginalName(),
            'ready' => collect($rows)->filter(fn ($r) => ! $r['errors'] && ! $r['warnings'])->count(),
            'warned' => collect($rows)->filter(fn ($r) => ! $r['errors'] && $r['warnings'])->count(),
            'failed' => collect($rows)->filter(fn ($r) => (bool) $r['errors'])->count(),
        ]);
    }

    public function confirm(Request $request)
    {
        $request->validate(['token' => ['required', 'string', 'size:32']]);

        $rows = Cache::pull($this->key($request->input('token')));
        if (! $rows) {
            return redirect()->route('people.import')->withErrors(['file' => 'This preview has expired. Upload the file again.']);
        }

        $includeWarned = $request->boolean('include_warnings');
        $toImport = collect($rows)->filter(fn ($r) => ! $r['errors'] && ($includeWarned || ! $r['warnings']));

        // Zinaingia kama "Imewasilishwa": NIN inatolewa kila moja ikiidhinishwa.
        $userId = $request->user()->id;
        $created = DB::transaction(function () use ($toImport, $userId) {
            return $toImport->map(function ($r) use ($userId) {
                $person = Person::create($r['data'] + [
                    'stage' => Person::SUBMITTED,
                    'created_by' => $userId,
                    'submitted_by' => $userId,
                    'submitted_at' => now(),
                ]);

                PersonReview::create(['person_id' => $person->id, 'user_id' => $userId, 'action' => 'submitted', 'note' => 'Imported from CSV']);

                return $person;
            });
        });

        ActivityLog::record('person.imported', "Imported {$created->count()} people from CSV (sent for approval)", null, [
            'ids' => $created->pluck('id')->all(),
            'skipped' => count($rows) - $created->count(),
        ]);

        return redirect()
            ->route('approvals.mine')
            ->with('success', "Imported {$created->count()} " . Str::plural('person', $created->count()) . ' and sent them for approval. ' .
                (count($rows) - $created->count() ? (count($rows) - $created->count()) . ' rows were skipped.' : ''));
    }

    private function key(string $token): string
    {
        return 'people-import:' . auth()->id() . ':' . $token;
    }
}
