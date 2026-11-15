<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\Person;
use App\Models\Region;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportController extends Controller
{
    /**
     * Vichujio vya ripoti kutoka kwenye query string.
     */
    private function filters(Request $request): array
    {
        return [
            'region' => $request->query('region'),
            'gender' => in_array($request->query('gender'), ['Male', 'Female'], true) ? $request->query('gender') : null,
            'status' => in_array($request->query('status'), ['Active', 'Inactive'], true) ? $request->query('status') : null,
            'from' => $request->date('from')?->toDateString(),
            'to' => $request->date('to')?->toDateString(),
        ];
    }

    private function query(array $f): Builder
    {
        return Person::query()
            ->when($f['region'], fn ($q, $r) => $q->where(fn ($w) => $w->where('region_id', $r)->orWhere('region', Region::find($r)?->name ?? '')))
            ->when($f['gender'], fn ($q, $g) => $q->where('gender', $g))
            ->when($f['status'], fn ($q, $s) => $q->where('status', $s))
            ->when($f['from'], fn ($q, $d) => $q->whereDate('created_at', '>=', $d))
            ->when($f['to'], fn ($q, $d) => $q->whereDate('created_at', '<=', $d));
    }

    public function index(Request $request)
    {
        $f = $this->filters($request);
        $regions = Region::orderBy('name')->get(['id', 'name']);

        $base = $this->query($f);

        $summary = [
            'total' => (clone $base)->count(),
            'male' => (clone $base)->where('gender', 'Male')->count(),
            'female' => (clone $base)->where('gender', 'Female')->count(),
            'with_photo' => (clone $base)->whereNotNull('profile_picture')->where('profile_picture', '!=', '')->count(),
        ];

        $byRegion = (clone $base)
            ->selectRaw("COALESCE(NULLIF(region, ''), 'Not specified') as name, COUNT(*) as total")
            ->groupBy('name')->orderByDesc('total')->limit(8)->pluck('total', 'name');

        $byEducation = (clone $base)
            ->selectRaw("COALESCE(NULLIF(education_level, ''), 'Not specified') as name, COUNT(*) as total")
            ->groupBy('name')->orderByDesc('total')->pluck('total', 'name');

        $preview = (clone $base)->latest()->limit(15)->get();

        return view('reports.index', compact('f', 'regions', 'summary', 'byRegion', 'byEducation', 'preview'));
    }

    /**
     * Pakua CSV (inafunguka moja kwa moja kwenye Excel).
     */
    public function export(Request $request): StreamedResponse
    {
        $f = $this->filters($request);
        $query = $this->query($f)->orderBy('id');
        $count = (clone $query)->count();

        ActivityLog::record('report.exported', "Exported {$count} records to CSV", null, array_filter($f));

        $columns = [
            'NIN' => 'nin', 'First name' => 'first_name', 'Middle name' => 'middle_name', 'Last name' => 'last_name',
            'Date of birth' => fn ($p) => $p->date_of_birth?->format('Y-m-d'), 'Gender' => 'gender', 'Nationality' => 'nationality',
            'Birth region' => 'birth_region', 'Birth district' => 'birth_district',
            'Region' => 'region', 'District' => 'district', 'Ward' => 'ward', 'Village/Street' => 'village',
            'Phone' => 'phone', 'Email' => 'email',
            'Father' => 'father_name', 'Father phone' => 'father_phone', 'Mother' => 'mother_name', 'Mother phone' => 'mother_phone',
            'Guardian' => 'guardian_name', 'Guardian phone' => 'guardian_phone',
            'Education level' => 'education_level', 'Primary school' => 'primary_school', 'Primary year' => 'primary_completion_year',
            'Secondary school' => 'secondary_school', 'Secondary year' => 'secondary_completion_year',
            'Institution' => 'higher_institution', 'Course' => 'course', 'Qualification' => 'qualification',
            'Status' => 'status', 'Registered at' => fn ($p) => $p->created_at?->format('Y-m-d H:i'),
        ];

        $filename = 'nin-records-' . now()->format('Ymd-His') . '.csv';

        return response()->streamDownload(function () use ($query, $columns) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF"); // BOM: Excel isome UTF-8 vizuri
            fputcsv($out, array_keys($columns));

            $query->chunk(500, function ($people) use ($out, $columns) {
                foreach ($people as $p) {
                    fputcsv($out, array_map(function ($col) use ($p) {
                        $value = is_callable($col) ? $col($p) : $p->{$col};

                        // NIN na simu kama maandishi, Excel isizigeuze kuwa namba (1.99E+19).
                        // Pia zuia CSV formula injection (=, +, -, @).
                        if (is_string($value) && preg_match('/^(\d{9,}|[=+\-@])/', $value)) {
                            return "\t" . $value;
                        }

                        return $value;
                    }, $columns));
                }
            });

            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /**
     * Toleo la kuchapisha (Print → Save as PDF).
     */
    public function print(Request $request)
    {
        $f = $this->filters($request);
        $people = $this->query($f)->orderBy('last_name')->orderBy('first_name')->get();
        $regionName = $f['region'] ? Region::find($f['region'])?->name : null;

        ActivityLog::record('report.printed', "Opened printable report ({$people->count()} records)", null, array_filter($f));

        return view('reports.print', compact('f', 'people', 'regionName'));
    }
}
