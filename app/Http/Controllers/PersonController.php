<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Support\ApprovalFlow;
use App\Support\DuplicateFinder;
use App\Support\QrCode;
use App\Models\Person;
use App\Models\Region;
use App\Models\District;
use App\Models\Ward;
use App\Models\Village;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class PersonController extends Controller
{
    /**
     * Display all registered people.
     */
    public function index(Request $request)
    {
        $search = trim((string) $request->query('q', ''));
        $gender = $request->query('gender');
        $photo = $request->query('photo');

        $people = Person::query()
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($q) use ($search) {
                    $q->where('nin', 'like', "%{$search}%")
                        ->orWhere('first_name', 'like', "%{$search}%")
                        ->orWhere('middle_name', 'like', "%{$search}%")
                        ->orWhere('last_name', 'like', "%{$search}%")
                        ->orWhere('region', 'like', "%{$search}%")
                        ->orWhere('phone', 'like', "%{$search}%");
                });
            })
            ->when(in_array($gender, ['Male', 'Female'], true), fn ($q) => $q->where('gender', $gender))
            ->when($photo === 'with', fn ($q) => $q->whereNotNull('profile_picture')->where('profile_picture', '!=', ''))
            ->when($photo === 'without', fn ($q) => $q->where(fn ($w) => $w->whereNull('profile_picture')->orWhere('profile_picture', '')))
            ->latest()
            ->paginate(12)
            ->withQueryString();

        $withoutPhoto = Person::where(fn ($w) => $w->whereNull('profile_picture')->orWhere('profile_picture', ''))->count();

        return view('people.index', compact('people', 'search', 'gender', 'photo', 'withoutPhoto'));
    }

    /**
     * Show registration form.
     */
    public function create()
    {
        $regions = Region::orderBy('name')->get(['id', 'name']);

        return view('people.create', compact('regions'));
    }

    /**
     * Show edit form (fomu ile ile ya usajili, ikiwa na taarifa za mtu).
     */
    public function edit(Person $person)
    {
        $this->ensureEditable($person);

        $regions = Region::orderBy('name')->get(['id', 'name']);

        return view('people.create', compact('regions', 'person'));
    }

    /**
     * Validation rules za pamoja (usajili + kuhariri).
     */
    private function rules(bool $editing): array
    {
        $year = date('Y');
        $yearRule = ['nullable', 'integer', 'digits:4', 'between:1950,' . $year];

        $rules = [
            // PERSONAL INFORMATION
            'first_name' => ['required', 'string', 'max:100'],
            'middle_name' => ['nullable', 'string', 'max:100'],
            'last_name' => ['required', 'string', 'max:100'],
            'date_of_birth' => ['required', 'date', 'before_or_equal:today'],
            'gender' => ['required', Rule::in(['Male', 'Female'])],
            'nationality' => ['required', 'string', 'max:100'],

            // BIRTH INFORMATION
            'birth_region_id' => ['nullable', 'exists:regions,id'],
            'birth_district_id' => ['nullable', 'required_with:birth_region_id', 'exists:districts,id'],

            // PROFILE PICTURE
            'profile_picture' => ['nullable', 'image', 'mimes:jpg,jpeg,png', 'max:2048'],

            // RESIDENTIAL LOCATION
            'region_id' => ['nullable', 'required_with:district_id,ward_id,village_id', 'exists:regions,id'],
            'district_id' => ['nullable', 'required_with:ward_id,village_id', 'exists:districts,id'],
            'ward_id' => ['nullable', 'required_with:village_id', 'exists:wards,id'],
            'village_id' => ['nullable', 'exists:villages,id'],

            // CONTACT INFORMATION
            'phone' => ['nullable', 'string', 'max:20'],
            'email' => ['nullable', 'email', 'max:255'],

            // PARENTS / GUARDIAN
            'father_name' => ['nullable', 'string', 'max:255'],
            'father_phone' => ['nullable', 'string', 'max:20'],
            'mother_name' => ['nullable', 'string', 'max:255'],
            'mother_phone' => ['nullable', 'string', 'max:20'],
            'guardian_name' => ['nullable', 'string', 'max:255'],
            'guardian_phone' => ['nullable', 'string', 'max:20'],

            // EDUCATION
            'education_level' => ['nullable', 'string', 'max:100'],
            'primary_school' => ['nullable', 'string', 'max:255'],
            'primary_completion_year' => $yearRule,
            'secondary_school' => ['nullable', 'string', 'max:255'],
            'secondary_completion_year' => $yearRule,
            'higher_institution' => ['nullable', 'string', 'max:255'],
            'course' => ['nullable', 'string', 'max:255'],
            'qualification' => ['nullable', 'string', 'max:255'],
        ];

        if ($editing) {
            $rules['status'] = ['required', Rule::in(['Active', 'Inactive'])];
        }

        return $rules;
    }

    /**
     * Validate, hakiki mpangilio wa maeneo, na badilisha ID kuwa majina.
     * ID zinahifadhiwa pamoja na majina (foreign keys).
     */
    private function validatedData(Request $request, bool $editing): array
    {
        $data = $request->validate($this->rules($editing));

        $hierarchy = [
            // [field, parent field, model, parent column, message]
            ['birth_district_id', 'birth_region_id', District::class, 'region_id', 'The selected birth district does not belong to the selected birth region.'],
            ['district_id', 'region_id', District::class, 'region_id', 'The selected district does not belong to the selected region.'],
            ['ward_id', 'district_id', Ward::class, 'district_id', 'The selected ward does not belong to the selected district.'],
            ['village_id', 'ward_id', Village::class, 'ward_id', 'The selected village/street does not belong to the selected ward.'],
        ];

        foreach ($hierarchy as [$field, $parent, $model, $column, $message]) {
            if (!empty($data[$field])
                && !$model::where('id', $data[$field])->where($column, $data[$parent] ?? null)->exists()) {
                throw ValidationException::withMessages([$field => $message]);
            }
        }

        $name = fn (string $model, ?string $id) => $id ? $model::findOrFail($id)->name : null;

        $data['birth_region'] = $name(Region::class, $data['birth_region_id'] ?? null);
        $data['birth_district'] = $name(District::class, $data['birth_district_id'] ?? null);
        $data['region'] = $name(Region::class, $data['region_id'] ?? null);
        $data['district'] = $name(District::class, $data['district_id'] ?? null);
        $data['ward'] = $name(Ward::class, $data['ward_id'] ?? null);
        $data['village'] = $name(Village::class, isset($data['village_id']) ? (string) $data['village_id'] : null);

        unset($data['profile_picture']);

        return $data;
    }

    /**
     * Save a new person.
     */
    public function store(Request $request)
    {
        $data = $this->validatedData($request, false);

        // Zuia usajili wa mara mbili: lazima mhudumu athibitishe kuwa ni mtu tofauti.
        $duplicates = app(DuplicateFinder::class)->candidates($data);
        if ($duplicates->isNotEmpty() && ! $request->boolean('confirm_not_duplicate')) {
            return back()
                ->withInput($request->except('profile_picture'))
                ->withErrors(['confirm_not_duplicate' => 'This person may already be registered. Check the matching records and confirm before registering.']);
        }

        // NIN haitolewi sasa: inatolewa rekodi ikiidhinishwa.
        $submit = $request->input('action') !== 'draft';
        $data['stage'] = Person::DRAFT;
        $data['created_by'] = $request->user()->id;

        if ($request->hasFile('profile_picture')) {
            $data['profile_picture'] = $request->file('profile_picture')->store('profile-pictures', 'public');
        }

        $person = Person::create($data);

        ActivityLog::record('person.created', "Registered {$person->full_name}" . ($submit ? '' : ' (draft)'), $person,
            $duplicates->isNotEmpty() ? ['confirmed_not_duplicate_of' => $duplicates->map(fn ($d) => $d['person']->nin)->all()] : []);

        if ($submit) {
            ApprovalFlow::move($person, Person::SUBMITTED, 'submitted', null, [
                'submitted_by' => $request->user()->id,
                'submitted_at' => now(),
            ]);
        }

        return redirect()
            ->route('people.show', $person->id)
            ->with('success', $submit
                ? 'Registered and sent for approval. The NIN will be issued once the record is approved.'
                : 'Saved as draft. Submit it for approval when it is complete.');
    }

    /**
     * Update an existing person. NIN haibadiliki.
     */
    public function update(Request $request, Person $person)
    {
        $this->ensureEditable($person);
        $data = $this->validatedData($request, true);

        if ($request->hasFile('profile_picture')) {
            $old = $person->profile_picture;
            $data['profile_picture'] = $request->file('profile_picture')->store('profile-pictures', 'public');

            if ($old) {
                Storage::disk('public')->delete($old);
            }
        }

        $person->fill($data);
        $changes = $this->describeChanges($person);
        $changed = array_keys($person->getDirty());
        $sensitive = $person->isApproved() ? array_values(array_intersect($changed, Person::SENSITIVE)) : [];
        $person->save();

        if ($changed) {
            ActivityLog::record(
                'person.updated',
                "Updated {$person->full_name}: " . implode(', ', array_map(fn ($c) => $c['label'], $changes)),
                $person,
                ['fields' => $changed, 'changes' => array_values($changes)]
            );
        }

        // Jina, tarehe ya kuzaliwa, jinsia au picha ya rekodi iliyoidhinishwa: idhini tena.
        if ($sensitive) {
            $this->sendForReapproval($person, $sensitive);

            return redirect()->route('people.show', $person)
                ->with('success', 'Details updated. Because ' . $this->fieldList($sensitive)
                    . ' changed, the record was sent for re-approval. The NIN stays the same.');
        }

        if ($request->input('action') === 'submit' && $person->canBeSubmittedBy($request->user())) {
            $again = $person->stage === Person::RETURNED;
            ApprovalFlow::move($person, Person::SUBMITTED, $again ? 'resubmitted' : 'submitted', null, [
                'submitted_by' => $request->user()->id,
                'submitted_at' => now(),
                'reviewer_id' => null,
                'review_note' => null,
            ]);

            return redirect()->route('people.show', $person)
                ->with('success', $again ? 'Corrections saved and sent back for approval.' : 'Saved and submitted for approval.');
        }

        return redirect()
            ->route('people.show', $person)
            ->with('success', $changed ? 'Person details updated successfully.' : 'No changes were made.');
    }

    /** Rekodi iliyowasilishwa, inayokaguliwa au iliyokataliwa haiwezi kuhaririwa. */
    private function ensureEditable(Person $person): void
    {
        abort_unless($person->canBeEditedBy(auth()->user()), 403, match ($person->stage) {
            Person::SUBMITTED, Person::IN_REVIEW => 'This record is waiting for approval and cannot be changed now.',
            Person::REJECTED => 'This record was rejected and cannot be changed.',
            default => 'You cannot change this record.',
        });
    }

    /** Badiliko nyeti kwenye rekodi iliyoidhinishwa: irudi kwenye foleni (NIN inabaki). */
    private function sendForReapproval(Person $person, array $fields): void
    {
        ApprovalFlow::move($person, Person::SUBMITTED, 'edited', 'Changed: ' . $this->fieldList($fields), [
            'submitted_by' => auth()->id(),
            'submitted_at' => now(),
            'reviewer_id' => null,
            'review_note' => null,
        ]);
    }

    private function fieldList(array $fields): string
    {
        return implode(', ', array_map(fn ($f) => $f === 'profile_picture' ? 'photo' : str_replace('_', ' ', $f), $fields));
    }

    /**
     * JSON kwa fomu ya usajili: watu waliopo wanaofanana na taarifa zilizoandikwa.
     */
    public function duplicates(Request $request, DuplicateFinder $finder): JsonResponse
    {
        $data = $request->validate([
            'first_name' => ['nullable', 'string', 'max:100'],
            'middle_name' => ['nullable', 'string', 'max:100'],
            'last_name' => ['nullable', 'string', 'max:100'],
            'date_of_birth' => ['nullable', 'date'],
            'phone' => ['nullable', 'string', 'max:20'],
            'except' => ['nullable', 'integer'],
        ]);

        return response()->json([
            'matches' => $finder->candidates($data, $data['except'] ?? null)->take(5)->map(fn ($m) => [
                'name' => $m['person']->full_name,
                'nin' => $m['person']->nin,
                'stage' => $m['person']->stageLabel(),
                'dob' => $m['person']->date_of_birth?->format('d M Y'),
                'region' => $m['person']->region,
                'phone' => $m['person']->phone,
                'reasons' => $m['reasons'],
                'url' => route('people.show', $m['person']),
            ])->values(),
        ]);
    }

    /**
     * Thamani ya zamani na mpya kwa kila sehemu iliyobadilika (kwa historia).
     * ID za maeneo hazionyeshwi: majina yake (region, district...) yanaonyesha badiliko.
     *
     * @return array<string, array{field: string, label: string, old: ?string, new: ?string}>
     */
    private function describeChanges(Person $person): array
    {
        $skip = ['updated_at', 'birth_region_id', 'birth_district_id', 'region_id', 'district_id', 'ward_id', 'village_id'];
        $labels = ['profile_picture' => 'photo', 'dob' => 'date of birth'];
        $changes = [];

        foreach ($person->getDirty() as $field => $new) {
            if (in_array($field, $skip, true)) {
                continue;
            }

            $old = $person->getOriginal($field);
            $format = function ($value) use ($field) {
                if ($value instanceof \DateTimeInterface) {
                    return $value->format('Y-m-d');
                }
                if ($field === 'profile_picture') {
                    return $value ? 'photo' : null;
                }

                return filled($value) ? mb_substr((string) $value, 0, 120) : null;
            };

            $changes[$field] = [
                'field' => $field,
                'label' => $labels[$field] ?? str_replace('_', ' ', $field),
                'old' => $format($old),
                'new' => $format($new),
            ];
        }

        // Kama ID tu ndizo zilibadilika (jina lilibaki lilelile), bado taja eneo.
        if (! $changes && $person->isDirty()) {
            foreach (array_keys($person->getDirty()) as $field) {
                if ($field !== 'updated_at') {
                    $changes[$field] = ['field' => $field, 'label' => str_replace(['_id', '_'], ['', ' '], $field) . ' link', 'old' => null, 'new' => null];
                }
            }
        }

        return $changes;
    }

    /**
     * Display one person's information.
     */
    public function show(Person $person)
    {
        $history = ActivityLog::with('user')
            ->where('subject_type', Person::class)
            ->where('subject_id', $person->id)
            ->latest('id')
            ->take(6)
            ->get();

        // QR ya kadi (rekodi zilizoidhinishwa tu): kiungo kilichosainiwa cha ukurasa wa uthibitisho.
        $verifyUrl = $person->isApproved() ? CardVerificationController::urlFor($person) : null;
        $qrSvg = $verifyUrl ? QrCode::svg($verifyUrl, 200, '#14532d') : null;

        $person->load(['creator:id,name', 'submitter:id,name', 'reviewer:id,name']);
        $reviews = $person->reviews()->with('user:id,name')->get();

        // Kwa anayeidhinisha: rekodi zinazofanana (huenda ni mtu yuleyule).
        $matches = ! $person->isApproved() && auth()->user()->canApprove()
            ? app(DuplicateFinder::class)->candidates($person->only(['first_name', 'middle_name', 'last_name', 'date_of_birth', 'phone']), $person->id)->take(5)
            : collect();

        return view('people.show', compact('person', 'history', 'verifyUrl', 'qrSvg', 'reviews', 'matches'));
    }

    /**
     * Badilisha picha ya profaili (picha ya zamani inafutwa).
     */
    public function updatePhoto(Request $request, Person $person)
    {
        $this->ensureEditable($person);

        $request->validate([
            'profile_picture' => ['required', 'image', 'mimes:jpg,jpeg,png', 'max:2048'],
        ]);

        $oldPath = $person->profile_picture;

        $person->update([
            'profile_picture' => $request->file('profile_picture')->store('profile-pictures', 'public'),
        ]);

        if ($oldPath && Storage::disk('public')->exists($oldPath)) {
            Storage::disk('public')->delete($oldPath);
        }

        ActivityLog::record('photo.updated', ($oldPath ? 'Changed' : 'Added') . " profile picture for {$person->full_name}", $person);

        if ($person->isApproved()) {
            $this->sendForReapproval($person, ['profile_picture']);

            return redirect()->route('people.show', $person)
                ->with('success', 'Profile picture updated and sent for re-approval.');
        }

        return redirect()
            ->route('people.show', $person)
            ->with('success', 'Profile picture updated successfully.');
    }

    /**
     * Ondoa picha ya profaili.
     */
    public function destroyPhoto(Person $person)
    {
        $this->ensureEditable($person);

        if ($person->profile_picture) {
            Storage::disk('public')->delete($person->profile_picture);

            $person->update(['profile_picture' => null]);

            ActivityLog::record('photo.removed', "Removed profile picture for {$person->full_name}", $person);
        }

        return redirect()
            ->route('people.show', $person)
            ->with('success', 'Profile picture removed.');
    }

    /**
     * Hifadhi sahihi ya mwenye kitambulisho: imechorwa kwenye pad
     * (data URL ya PNG) au faili la picha lililopakiwa.
     */
    public function updateSignature(Request $request, Person $person)
    {
        $this->ensureEditable($person);

        $request->validate([
            'signature_data' => ['nullable', 'required_without:signature_file', 'string', 'max:1500000', 'starts_with:data:image/png;base64,'],
            'signature_file' => ['nullable', 'required_without:signature_data', 'image', 'mimes:png,jpg,jpeg', 'max:1024'],
        ]);

        if ($request->hasFile('signature_file')) {
            $path = $request->file('signature_file')->store('signatures', 'public');
        } else {
            $binary = base64_decode(substr($request->input('signature_data'), strlen('data:image/png;base64,')), true);
            $size = $binary !== false ? @getimagesizefromstring($binary) : false;

            if (! $size || $size[2] !== IMAGETYPE_PNG) {
                throw ValidationException::withMessages(['signature_data' => 'The signature image is not valid.']);
            }

            $path = 'signatures/' . Str::random(40) . '.png';
            Storage::disk('public')->put($path, $binary);
        }

        $oldPath = $person->signature;
        $person->update(['signature' => $path]);

        if ($oldPath && Storage::disk('public')->exists($oldPath)) {
            Storage::disk('public')->delete($oldPath);
        }

        ActivityLog::record('signature.updated', ($oldPath ? 'Changed' : 'Added') . " signature for {$person->full_name}", $person);

        return redirect()
            ->route('people.show', $person)
            ->withFragment('id-card')
            ->with('success', 'Signature saved successfully.');
    }

    public function destroySignature(Person $person)
    {
        $this->ensureEditable($person);

        if ($person->signature) {
            Storage::disk('public')->delete($person->signature);
            $person->update(['signature' => null]);

            ActivityLog::record('signature.removed', "Removed signature for {$person->full_name}", $person);
        }

        return redirect()
            ->route('people.show', $person)
            ->withFragment('id-card')
            ->with('success', 'Signature removed.');
    }

    /**
     * Search person using NIN and return full details.
     */
    public function lookup(string $nin)
    {
        if (!preg_match('/^[0-9]{20}$/', $nin)) {
            return response()->json([
                'success' => false,
                'message' => 'NIN must contain exactly 20 digits.',
            ], 422);
        }

        $person = Person::where('nin', $nin)->first();

        if (!$person) {
            return response()->json([
                'success' => false,
                'message' => 'Person not found.',
            ], 404);
        }

        return response()->json([
            'success' => true,

            'person' => [
                'nin' => $person->nin,
                'profile_url' => route('people.show', $person),
                'first_name' => $person->first_name,
                'middle_name' => $person->middle_name,
                'last_name' => $person->last_name,
                'full_name' => $person->full_name,
                'date_of_birth' => $person->date_of_birth?->format('Y-m-d'),
                'gender' => $person->gender,
                'nationality' => $person->nationality,

                'birth_region' => $person->birth_region,
                'birth_district' => $person->birth_district,

                'profile_picture' => $person->profile_picture
                    ? Storage::disk('public')->url($person->profile_picture)
                    : null,

                'region' => $person->region,
                'district' => $person->district,
                'ward' => $person->ward,
                'village' => $person->village,

                'phone' => $person->phone,
                'email' => $person->email,

                'father_name' => $person->father_name,
                'father_phone' => $person->father_phone,
                'mother_name' => $person->mother_name,
                'mother_phone' => $person->mother_phone,
                'guardian_name' => $person->guardian_name,
                'guardian_phone' => $person->guardian_phone,

                'education_level' => $person->education_level,
                'primary_school' => $person->primary_school,
                'primary_completion_year' => $person->primary_completion_year,
                'secondary_school' => $person->secondary_school,
                'secondary_completion_year' => $person->secondary_completion_year,
                'higher_institution' => $person->higher_institution,
                'course' => $person->course,
                'qualification' => $person->qualification,

                'status' => $person->status,
            ],
        ]);
    }
}
