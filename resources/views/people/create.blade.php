@extends('layouts.app')

@php
    $person = $person ?? null;
    $editing = $person !== null;

    // Thamani ya sehemu: old() kwanza (baada ya kosa), kisha taarifa za mtu (edit), kisha default.
    $val = function (string $field, $default = null) use ($person) {
        $current = $person?->{$field};
        if ($current instanceof \Carbon\CarbonInterface) {
            $current = $current->format('Y-m-d');
        }
        return old($field, $current ?? $default);
    };
@endphp

@section('title', $editing ? 'Edit ' . $person->full_name : 'Register New Person')

@section('page_title', $editing ? 'Edit Person' : 'Register New Person')

@php
    // Sehemu za kila hatua: zinatumika kufungua hatua yenye kosa baada ya validation.
    $stepFields = [
        1 => ['profile_picture', 'first_name', 'middle_name', 'last_name', 'date_of_birth', 'gender', 'nationality', 'status'],
        2 => ['birth_region_id', 'birth_district_id', 'region_id', 'district_id', 'ward_id', 'village_id', 'phone', 'email'],
        3 => ['father_name', 'father_phone', 'mother_name', 'mother_phone', 'guardian_name', 'guardian_phone'],
        4 => ['education_level', 'primary_school', 'primary_completion_year', 'secondary_school', 'secondary_completion_year', 'higher_institution', 'course', 'qualification'],
    ];

    $startStep = 1;
    foreach ($stepFields as $step => $fields) {
        if ($errors->hasAny($fields)) { $startStep = $step; break; }
    }

    $inv = fn ($field) => $errors->has($field) ? 'is-invalid' : '';

    $steps = [
        1 => ['Personal details', 'Name, birth date & photo', 'bi-person-vcard'],
        2 => ['Location & contact', 'Birth place & residence', 'bi-geo-alt'],
        3 => ['Family', 'Parents & guardian', 'bi-people'],
        4 => ['Education', 'Schools & qualifications', 'bi-mortarboard'],
        5 => ['Review & submit', 'Confirm and generate NIN', 'bi-patch-check'],
    ];
@endphp

@push('styles')
<style>
    .wiz {
        --accent: #16a34a;
        --accent-dark: #15803d;
        --accent-soft: #dcfce7;
        --ink: #0f172a;
        --text: #334155;
        --muted: #94a3b8;
        --line: #e5e7eb;
        --field: #f8fafc;
        --danger: #dc2626;

        display: grid;
        grid-template-columns: 290px minmax(0, 1fr);
        gap: 22px;
        align-items: start;
    }

    /* ---------- STEPPER ---------- */
    .stepper {
        position: sticky; top: 100px;
        background: #fff; border: 1px solid var(--line); border-radius: 18px;
        padding: 22px 18px;
    }
    .stepper-head { padding: 0 6px 16px; border-bottom: 1px solid var(--line); margin-bottom: 14px; }
    .stepper-head h2 { margin: 0 0 4px; font-size: 17px; color: var(--ink); }
    .stepper-head p { margin: 0; font-size: 12.5px; color: var(--muted); line-height: 1.5; }

    .progress { height: 6px; border-radius: 999px; background: #f1f5f9; margin-top: 14px; overflow: hidden; }
    .progress span { display: block; height: 100%; background: linear-gradient(90deg, var(--accent), #4ade80); border-radius: 999px; transition: width .35s ease; }
    .progress-label { display: flex; justify-content: space-between; font-size: 12px; color: var(--muted); margin-top: 7px; }

    .steps { list-style: none; margin: 0; padding: 0; display: flex; flex-direction: column; gap: 4px; }
    .step-btn {
        width: 100%; display: flex; align-items: center; gap: 12px;
        background: none; border: 0; text-align: left; cursor: pointer;
        padding: 10px; border-radius: 12px; font: inherit;
        transition: background .15s;
    }
    .step-btn:hover { background: #f8fafc; }
    .step-dot {
        width: 36px; height: 36px; border-radius: 11px; flex-shrink: 0;
        display: grid; place-items: center; font-size: 16px;
        background: #f1f5f9; color: var(--muted);
        transition: all .2s;
    }
    .step-btn b { display: block; font-size: 13.5px; color: var(--text); font-weight: 650; }
    .step-btn small { font-size: 12px; color: var(--muted); }

    .step-btn.active { background: var(--accent-soft); }
    .step-btn.active .step-dot { background: var(--accent); color: #fff; box-shadow: 0 6px 14px -6px rgba(22,163,74,.7); }
    .step-btn.active b { color: var(--accent-dark); }
    .step-btn.done .step-dot { background: #bbf7d0; color: var(--accent-dark); }
    .step-btn.has-error .step-dot { background: #fee2e2; color: var(--danger); }

    .stepper-note {
        margin-top: 16px; padding: 12px; border-radius: 12px;
        background: #f8fafc; font-size: 12px; color: #64748b; line-height: 1.5;
        display: flex; gap: 8px;
    }
    .stepper-note i { color: var(--accent); }

    /* ---------- PANELS ---------- */
    .panel-wrap { background: #fff; border: 1px solid var(--line); border-radius: 18px; overflow: hidden; }

    .panel { display: none; padding: 26px 28px 8px; animation: fadeIn .3s ease; }
    .panel.active { display: block; }
    @keyframes fadeIn { from { opacity: 0; transform: translateY(6px); } to { opacity: 1; transform: none; } }

    .panel-head { display: flex; align-items: center; gap: 14px; margin-bottom: 22px; }
    .panel-ico {
        width: 46px; height: 46px; border-radius: 13px; flex-shrink: 0;
        display: grid; place-items: center; font-size: 21px;
        background: var(--accent-soft); color: var(--accent);
    }
    .panel-head small { font-size: 12px; font-weight: 700; color: var(--accent); letter-spacing: .6px; text-transform: uppercase; }
    .panel-head h3 { margin: 2px 0 0; font-size: 19px; color: var(--ink); }

    .group-title {
        display: flex; align-items: center; gap: 8px;
        font-size: 13px; font-weight: 700; color: var(--ink);
        margin: 22px 0 12px;
    }
    .group-title::after { content: ''; flex: 1; height: 1px; background: var(--line); }
    .group-title i { color: var(--accent); }

    .fgrid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 16px 18px; }
    .fgrid.three { grid-template-columns: repeat(3, minmax(0, 1fr)); }
    .span-2 { grid-column: span 2; }

    .field { display: flex; flex-direction: column; gap: 6px; min-width: 0; }
    .field label { font-size: 13px; font-weight: 600; color: var(--text); }
    .field label .req { color: var(--danger); margin-left: 2px; }
    .field label .opt { color: var(--muted); font-weight: 500; font-size: 12px; margin-left: 4px; }

    .control { position: relative; }
    .control > i {
        position: absolute; left: 13px; top: 50%; transform: translateY(-50%);
        color: var(--muted); font-size: 15px; pointer-events: none; transition: color .15s;
    }
    .control input, .control select {
        width: 100%; height: 46px;
        padding: 0 14px 0 40px;
        border: 1.5px solid var(--line); border-radius: 12px;
        background: var(--field); color: var(--ink);
        font: inherit; font-size: 14px;
        outline: none; transition: border-color .15s, box-shadow .15s, background .15s;
        appearance: none; -webkit-appearance: none;
    }
    .control select {
        background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 16 16' fill='%2394a3b8'%3E%3Cpath d='M1.6 5.1a.75.75 0 0 1 1.06 0L8 10.4l5.34-5.3a.75.75 0 1 1 1.06 1.06l-5.87 5.83a.75.75 0 0 1-1.06 0L1.6 6.16a.75.75 0 0 1 0-1.06z'/%3E%3C/svg%3E");
        background-repeat: no-repeat; background-position: right 14px center;
        padding-right: 36px; cursor: pointer;
    }
    .control input:hover, .control select:hover { border-color: #cbd5e1; }
    .control input:focus, .control select:focus { border-color: var(--accent); background: #fff; box-shadow: 0 0 0 4px rgba(22,163,74,.12); }
    .control:focus-within > i { color: var(--accent); }
    .control select:disabled { opacity: .55; cursor: not-allowed; }

    .is-invalid, .control .is-invalid, .was-checked :invalid { border-color: var(--danger) !important; background: #fef2f2 !important; }
    .err { font-size: 12px; color: var(--danger); display: flex; gap: 5px; align-items: center; }
    .help { font-size: 12px; color: var(--muted); }

    /* Gender cards */
    .choice { display: grid; grid-template-columns: 1fr 1fr; gap: 10px; position: relative; }
    .choice input { position: absolute; opacity: 0; pointer-events: none; width: 1px; height: 1px; left: 0; top: 0; }
    .choice label {
        height: 46px; border: 1.5px solid var(--line); border-radius: 12px; background: var(--field);
        display: flex; align-items: center; justify-content: center; gap: 8px;
        font-weight: 600; font-size: 14px; color: var(--text); cursor: pointer; transition: all .15s;
    }
    .choice label:hover { border-color: #cbd5e1; }
    .choice input:checked + label { border-color: var(--accent); background: var(--accent-soft); color: var(--accent-dark); box-shadow: 0 0 0 4px rgba(22,163,74,.1); }
    .choice input:focus-visible + label { outline: 2px solid var(--accent); outline-offset: 2px; }

    /* Photo dropzone */
    .photo {
        display: flex; align-items: center; gap: 20px;
        padding: 18px; border: 2px dashed #d1d5db; border-radius: 16px;
        background: #fafafa; margin-bottom: 6px; cursor: pointer; transition: all .2s;
    }
    .photo:hover, .photo.drag { border-color: var(--accent); background: #f0fdf4; }
    .photo-frame {
        width: 92px; height: 92px; border-radius: 18px; flex-shrink: 0; overflow: hidden;
        background: linear-gradient(135deg, #dcfce7, #bbf7d0); color: var(--accent);
        display: grid; place-items: center; font-size: 38px; position: relative;
    }
    .photo-frame img { width: 100%; height: 100%; object-fit: cover; display: none; position: absolute; inset: 0; }
    .photo b { display: block; font-size: 14.5px; color: var(--ink); margin-bottom: 3px; }
    .photo p { margin: 0 0 10px; font-size: 13px; color: #64748b; }
    .photo-btn {
        display: inline-flex; align-items: center; gap: 6px; font-size: 13px; font-weight: 700;
        padding: 7px 13px; border-radius: 9px; background: #fff; border: 1px solid var(--line); color: var(--text);
    }
    button.photo-btn { font-family: inherit; cursor: pointer; margin: 0 6px 6px 0; width: auto !important; display: inline-flex !important; }
    .photo-btn.cam { background: var(--accent); border-color: var(--accent); color: #fff; }
    .photo-btn.cam:hover { background: var(--accent-dark); }
    .photo-remove { display: none; margin-left: 6px; color: var(--danger); background: none; border: 0; font: inherit; font-size: 13px; font-weight: 600; cursor: pointer; }
    .photo.has-image .photo-remove { display: inline; }
    #profile_picture { display: none; }

    /* NIN preview banner */
    .nin-banner {
        display: flex; align-items: center; gap: 14px;
        margin-top: 20px; padding: 14px 16px; border-radius: 14px;
        background: linear-gradient(120deg, #052e16, #15803d); color: #fff;
    }
    .nin-banner i { font-size: 24px; opacity: .9; }
    .nin-banner small { display: block; font-size: 12px; opacity: .75; }
    .nin-banner b { font-family: ui-monospace, Menlo, monospace; font-size: 16px; letter-spacing: 1.5px; }

    /* Review */
    .review { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 16px; }
    .review-card { border: 1px solid var(--line); border-radius: 14px; padding: 16px; }
    .review-card h4 { display: flex; align-items: center; justify-content: space-between; margin: 0 0 12px; font-size: 14px; color: var(--ink); }
    .review-card h4 button { background: none; border: 0; color: var(--accent); font: inherit; font-size: 12.5px; font-weight: 700; cursor: pointer; }
    .review-card dl { margin: 0; display: grid; grid-template-columns: auto 1fr; gap: 8px 14px; font-size: 13px; }
    .review-card dt { color: var(--muted); }
    .review-card dd { margin: 0; color: var(--ink); font-weight: 600; text-align: right; word-break: break-word; }
    .review-card dd.empty { color: #cbd5e1; font-weight: 500; }
    .review-person { display: flex; align-items: center; gap: 14px; grid-column: span 2; padding: 16px; border-radius: 14px; background: #f0fdf4; border: 1px solid #bbf7d0; }
    .review-person .photo-frame { width: 64px; height: 64px; font-size: 26px; border-radius: 14px; }
    .review-person b { font-size: 18px; color: var(--ink); display: block; }
    .review-person small { color: #64748b; font-size: 13px; }

    .confirm { display: flex; gap: 10px; align-items: flex-start; margin-top: 18px; padding: 14px; border-radius: 12px; background: #fffbeb; border: 1px solid #fde68a; font-size: 13px; color: #78350f; cursor: pointer; }
    .confirm input { margin-top: 2px; accent-color: var(--accent); width: 16px; height: 16px; }

    /* Footer */
    .wiz-foot {
        display: flex; align-items: center; justify-content: space-between; gap: 12px;
        padding: 18px 28px; margin-top: 22px; border-top: 1px solid var(--line); background: #fcfcfd;
    }
    .btn-w {
        display: inline-flex; align-items: center; gap: 8px;
        height: 44px; padding: 0 20px; border-radius: 12px;
        font: inherit; font-size: 14px; font-weight: 700; cursor: pointer;
        text-decoration: none; border: 1.5px solid transparent; transition: all .15s;
    }
    .btn-ghost { background: #fff; border-color: var(--line); color: var(--text); }
    .btn-ghost:hover { border-color: #cbd5e1; background: #f8fafc; }
    .btn-primary { background: var(--accent); color: #fff; box-shadow: 0 8px 18px -8px rgba(22,163,74,.7); }
    .btn-primary:hover { background: var(--accent-dark); transform: translateY(-1px); }
    .btn-primary:disabled { opacity: .55; cursor: not-allowed; transform: none; }
    .foot-right { display: flex; gap: 10px; }
    .hidden { display: none !important; }

    @media (max-width: 1100px) {
        .wiz { grid-template-columns: 1fr; }
        .stepper { position: static; padding: 16px; }
        .steps { flex-direction: row; overflow-x: auto; gap: 6px; }
        .step-btn { flex-direction: column; text-align: center; gap: 6px; min-width: 96px; }
        .step-btn small { display: none; }
        .step-btn b { font-size: 12px; }
        .stepper-note { display: none; }
    }
    @media (max-width: 680px) {
        .fgrid, .fgrid.three, .review { grid-template-columns: 1fr; }
        .span-2, .review-person { grid-column: auto; }
        .panel { padding: 20px 18px 4px; }
        .wiz-foot { padding: 16px 18px; flex-wrap: wrap; }
        .photo { flex-direction: column; text-align: center; }
    }
</style>
@endpush

@section('content')

<div class="wiz" id="wizard" data-start="{{ $startStep }}">

    {{-- ================= STEPPER ================= --}}
    <aside class="stepper">
        <div class="stepper-head">
            <h2>{{ $editing ? 'Edit ' . $person->first_name : 'New registration' }}</h2>
            <p>{{ $editing ? 'Update the details below. The NIN stays the same.' : 'Fill in the details below. A unique 20-digit NIN is generated automatically.' }}</p>
            <div class="progress"><span id="progressBar" style="width:20%"></span></div>
            <div class="progress-label"><span id="progressText">Step 1 of 5</span><span id="progressPct">20%</span></div>
        </div>

        <ol class="steps">
            @foreach ($steps as $n => [$title, $sub, $icon])
                <li>
                    <button type="button" class="step-btn {{ $n < 5 && $errors->hasAny($stepFields[$n]) ? 'has-error' : '' }}" data-goto="{{ $n }}">
                        <span class="step-dot"><i class="bi {{ $icon }}"></i></span>
                        <span><b>{{ $title }}</b><small>{{ $sub }}</small></span>
                    </button>
                </li>
            @endforeach
        </ol>

        <div class="stepper-note">
            <i class="bi bi-shield-lock-fill"></i>
            <span>Fields marked <b style="color:#dc2626">*</b> are required. Everything else can be added later.</span>
        </div>
    </aside>

    {{-- ================= FORM ================= --}}
    <form method="POST" action="{{ $editing ? route('people.update', $person) : route('people.store') }}" enctype="multipart/form-data"
          class="panel-wrap" id="regForm" novalidate>
        @csrf
        @if ($editing) @method('PUT') @endif

        {{-- STEP 1: PERSONAL --}}
        <section class="panel" data-step="1">
            <div class="panel-head">
                <span class="panel-ico"><i class="bi bi-person-vcard"></i></span>
                <div><small>Step 1</small><h3>Personal details</h3></div>
            </div>

            <label class="photo" id="dropzone" for="profile_picture">
                <span class="photo-frame">
                    @php $existingPhoto = $editing && $person->profile_picture && Storage::disk('public')->exists($person->profile_picture) ? asset('storage/' . $person->profile_picture) : null; @endphp
                    <i class="bi bi-person-fill" id="photoPlaceholder" @if ($existingPhoto) style="display:none" @endif></i>
                    <img id="photoPreview" alt="Profile picture preview" @if ($existingPhoto) src="{{ $existingPhoto }}" style="display:block" @endif>
                </span>
                <span>
                    <b>Profile picture</b>
                    <p>Take a photo with the camera, or drag & drop / browse for one. JPG or PNG, max 2 MB.</p>
                    <button type="button" class="photo-btn cam" id="photoCamera"><i class="bi bi-camera"></i> Take photo</button>
                    <span class="photo-btn"><i class="bi bi-upload"></i> {{ $existingPhoto ? 'Replace photo' : 'Choose photo' }}</span>
                    <button type="button" class="photo-remove" id="photoRemove">Remove</button>
                </span>
            </label>
            <input type="file" name="profile_picture" id="profile_picture" accept=".jpg,.jpeg,.png,image/jpeg,image/png">
            @error('profile_picture')<span class="err"><i class="bi bi-exclamation-circle"></i>{{ $message }}</span>@enderror

            <div class="group-title"><i class="bi bi-fonts"></i> Full name</div>
            <div class="fgrid three">
                <div class="field">
                    <label for="first_name">First name<span class="req">*</span></label>
                    <div class="control"><i class="bi bi-person"></i>
                        <input type="text" name="first_name" id="first_name" value="{{ $val('first_name') }}" class="{{ $inv('first_name') }}" placeholder="e.g. Amina" maxlength="100" required>
                    </div>
                    @error('first_name')<span class="err"><i class="bi bi-exclamation-circle"></i>{{ $message }}</span>@enderror
                </div>
                <div class="field">
                    <label for="middle_name">Middle name<span class="opt">optional</span></label>
                    <div class="control"><i class="bi bi-person"></i>
                        <input type="text" name="middle_name" id="middle_name" value="{{ $val('middle_name') }}" class="{{ $inv('middle_name') }}" placeholder="e.g. Juma" maxlength="100">
                    </div>
                    @error('middle_name')<span class="err"><i class="bi bi-exclamation-circle"></i>{{ $message }}</span>@enderror
                </div>
                <div class="field">
                    <label for="last_name">Last name<span class="req">*</span></label>
                    <div class="control"><i class="bi bi-person"></i>
                        <input type="text" name="last_name" id="last_name" value="{{ $val('last_name') }}" class="{{ $inv('last_name') }}" placeholder="e.g. Mrisho" maxlength="100" required>
                    </div>
                    @error('last_name')<span class="err"><i class="bi bi-exclamation-circle"></i>{{ $message }}</span>@enderror
                </div>
            </div>

            <div class="group-title"><i class="bi bi-info-circle"></i> Identity</div>
            <div class="fgrid three">
                <div class="field">
                    <label for="date_of_birth">Date of birth<span class="req">*</span></label>
                    <div class="control"><i class="bi bi-calendar-event"></i>
                        <input type="date" name="date_of_birth" id="date_of_birth" max="{{ date('Y-m-d') }}" value="{{ $val('date_of_birth') }}" class="{{ $inv('date_of_birth') }}" required>
                    </div>
                    <span class="help" id="ageHelp"></span>
                    @error('date_of_birth')<span class="err"><i class="bi bi-exclamation-circle"></i>{{ $message }}</span>@enderror
                </div>
                <div class="field">
                    <label>Gender<span class="req">*</span></label>
                    <div class="choice">
                        <input type="radio" name="gender" id="gender_male" value="Male" {{ $val('gender') === 'Male' ? 'checked' : '' }} required>
                        <label for="gender_male" class="{{ $inv('gender') }}"><i class="bi bi-gender-male"></i> Male</label>
                        <input type="radio" name="gender" id="gender_female" value="Female" {{ $val('gender') === 'Female' ? 'checked' : '' }}>
                        <label for="gender_female" class="{{ $inv('gender') }}"><i class="bi bi-gender-female"></i> Female</label>
                    </div>
                    @error('gender')<span class="err"><i class="bi bi-exclamation-circle"></i>{{ $message }}</span>@enderror
                </div>
                <div class="field">
                    <label for="nationality">Nationality<span class="req">*</span></label>
                    <div class="control"><i class="bi bi-flag"></i>
                        <input type="text" name="nationality" id="nationality" value="{{ $val('nationality', 'Tanzanian') }}" class="{{ $inv('nationality') }}" maxlength="100" required>
                    </div>
                    @error('nationality')<span class="err"><i class="bi bi-exclamation-circle"></i>{{ $message }}</span>@enderror
                </div>
                @if ($editing)
                    <div class="field">
                        <label>Record status<span class="req">*</span></label>
                        <div class="choice">
                            <input type="radio" name="status" id="status_active" value="Active" {{ $val('status', 'Active') === 'Active' ? 'checked' : '' }} required>
                            <label for="status_active"><i class="bi bi-check-circle"></i> Active</label>
                            <input type="radio" name="status" id="status_inactive" value="Inactive" {{ $val('status') === 'Inactive' ? 'checked' : '' }}>
                            <label for="status_inactive"><i class="bi bi-pause-circle"></i> Inactive</label>
                        </div>
                        <span class="help">Inactive records cannot pass NIN verification.</span>
                        @error('status')<span class="err"><i class="bi bi-exclamation-circle"></i>{{ $message }}</span>@enderror
                    </div>
                @endif
            </div>

            <div class="nin-banner">
                <i class="bi bi-fingerprint"></i>
                <div>
                    @if ($editing)
                        <small>National Identification Number · cannot be changed</small>
                        <b>{{ $person->nin }}</b>
                    @else
                        <small>NIN preview: date part is taken from the birth date</small>
                        <b id="ninPreview">YYYYMMDD ············</b>
                    @endif
                </div>
            </div>
        </section>

        {{-- STEP 2: LOCATION --}}
        <section class="panel" data-step="2">
            <div class="panel-head">
                <span class="panel-ico"><i class="bi bi-geo-alt"></i></span>
                <div><small>Step 2</small><h3>Location & contact</h3></div>
            </div>

            <div class="group-title"><i class="bi bi-hospital"></i> Place of birth</div>
            <div class="fgrid">
                <div class="field">
                    <label for="birth_region_id">Region of birth</label>
                    <div class="control"><i class="bi bi-map"></i>
                        <select name="birth_region_id" id="birth_region_id" class="{{ $inv('birth_region_id') }}">
                            <option value="">Select region</option>
                            @foreach ($regions as $region)
                                <option value="{{ $region->id }}" {{ $val('birth_region_id') == $region->id ? 'selected' : '' }}>{{ $region->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    @error('birth_region_id')<span class="err"><i class="bi bi-exclamation-circle"></i>{{ $message }}</span>@enderror
                </div>
                <div class="field">
                    <label for="birth_district_id">District of birth</label>
                    <div class="control"><i class="bi bi-signpost-split"></i>
                        <select name="birth_district_id" id="birth_district_id" class="{{ $inv('birth_district_id') }}"
                                data-url="{{ route('locations.districts', ['regionId' => '__ID__']) }}"
                                data-old="{{ $val('birth_district_id') }}" disabled>
                            <option value="">Select district</option>
                        </select>
                    </div>
                    @error('birth_district_id')<span class="err"><i class="bi bi-exclamation-circle"></i>{{ $message }}</span>@enderror
                </div>
            </div>

            <div class="group-title"><i class="bi bi-house-door"></i> Current residence</div>
            <div class="fgrid">
                <div class="field">
                    <label for="region_id">Region</label>
                    <div class="control"><i class="bi bi-map"></i>
                        <select name="region_id" id="region_id" class="{{ $inv('region_id') }}">
                            <option value="">Select region</option>
                            @foreach ($regions as $region)
                                <option value="{{ $region->id }}" {{ $val('region_id') == $region->id ? 'selected' : '' }}>{{ $region->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    @error('region_id')<span class="err"><i class="bi bi-exclamation-circle"></i>{{ $message }}</span>@enderror
                </div>
                <div class="field">
                    <label for="district_id">District</label>
                    <div class="control"><i class="bi bi-signpost-split"></i>
                        <select name="district_id" id="district_id" class="{{ $inv('district_id') }}"
                                data-url="{{ route('locations.districts', ['regionId' => '__ID__']) }}"
                                data-old="{{ $val('district_id') }}" disabled>
                            <option value="">Select district</option>
                        </select>
                    </div>
                    @error('district_id')<span class="err"><i class="bi bi-exclamation-circle"></i>{{ $message }}</span>@enderror
                </div>
                <div class="field">
                    <label for="ward_id">Ward</label>
                    <div class="control"><i class="bi bi-pin-map"></i>
                        <select name="ward_id" id="ward_id" class="{{ $inv('ward_id') }}"
                                data-url="{{ route('locations.wards', ['districtId' => '__ID__']) }}"
                                data-old="{{ $val('ward_id') }}" disabled>
                            <option value="">Select ward</option>
                        </select>
                    </div>
                    @error('ward_id')<span class="err"><i class="bi bi-exclamation-circle"></i>{{ $message }}</span>@enderror
                </div>
                <div class="field">
                    <label for="village_id">Village / Street</label>
                    <div class="control"><i class="bi bi-signpost"></i>
                        <select name="village_id" id="village_id" class="{{ $inv('village_id') }}"
                                data-url="{{ route('locations.villages', ['wardId' => '__ID__']) }}"
                                data-old="{{ $val('village_id') }}" disabled>
                            <option value="">Select village / street</option>
                        </select>
                    </div>
                    <span class="help" id="villageHelp">Select a ward to see its villages and streets.</span>
                    @error('village_id')<span class="err"><i class="bi bi-exclamation-circle"></i>{{ $message }}</span>@enderror
                </div>
            </div>

            <div class="group-title"><i class="bi bi-telephone"></i> Contact</div>
            <div class="fgrid">
                <div class="field">
                    <label for="phone">Phone number<span class="opt">optional</span></label>
                    <div class="control"><i class="bi bi-phone"></i>
                        <input type="tel" name="phone" id="phone" value="{{ $val('phone') }}" class="{{ $inv('phone') }}" placeholder="e.g. 0712 345 678" maxlength="20">
                    </div>
                    @error('phone')<span class="err"><i class="bi bi-exclamation-circle"></i>{{ $message }}</span>@enderror
                </div>
                <div class="field">
                    <label for="email">Email address<span class="opt">optional</span></label>
                    <div class="control"><i class="bi bi-envelope"></i>
                        <input type="email" name="email" id="email" value="{{ $val('email') }}" class="{{ $inv('email') }}" placeholder="name@example.com" maxlength="255">
                    </div>
                    @error('email')<span class="err"><i class="bi bi-exclamation-circle"></i>{{ $message }}</span>@enderror
                </div>
            </div>
        </section>

        {{-- STEP 3: FAMILY --}}
        <section class="panel" data-step="3">
            <div class="panel-head">
                <span class="panel-ico"><i class="bi bi-people"></i></span>
                <div><small>Step 3</small><h3>Parents & guardian</h3></div>
            </div>

            @foreach ([
                'father' => ["Father's information", 'bi-person-standing'],
                'mother' => ["Mother's information", 'bi-person-standing-dress'],
                'guardian' => ["Guardian's information", 'bi-shield-check'],
            ] as $who => [$title, $icon])
                <div class="group-title"><i class="bi {{ $icon }}"></i> {{ $title }}</div>
                <div class="fgrid">
                    <div class="field">
                        <label for="{{ $who }}_name">Full name<span class="opt">optional</span></label>
                        <div class="control"><i class="bi bi-person"></i>
                            <input type="text" name="{{ $who }}_name" id="{{ $who }}_name" value="{{ $val($who . '_name') }}" class="{{ $inv($who . '_name') }}" placeholder="Enter {{ $who }}'s full name" maxlength="255">
                        </div>
                        @error($who . '_name')<span class="err"><i class="bi bi-exclamation-circle"></i>{{ $message }}</span>@enderror
                    </div>
                    <div class="field">
                        <label for="{{ $who }}_phone">Phone number<span class="opt">optional</span></label>
                        <div class="control"><i class="bi bi-phone"></i>
                            <input type="tel" name="{{ $who }}_phone" id="{{ $who }}_phone" value="{{ $val($who . '_phone') }}" class="{{ $inv($who . '_phone') }}" placeholder="e.g. 0754 000 000" maxlength="20">
                        </div>
                        @error($who . '_phone')<span class="err"><i class="bi bi-exclamation-circle"></i>{{ $message }}</span>@enderror
                    </div>
                </div>
            @endforeach
        </section>

        {{-- STEP 4: EDUCATION --}}
        <section class="panel" data-step="4">
            <div class="panel-head">
                <span class="panel-ico"><i class="bi bi-mortarboard"></i></span>
                <div><small>Step 4</small><h3>Education</h3></div>
            </div>

            <div class="fgrid">
                <div class="field">
                    <label for="education_level">Highest education level<span class="opt">optional</span></label>
                    <div class="control"><i class="bi bi-bar-chart-steps"></i>
                        <select name="education_level" id="education_level" class="{{ $inv('education_level') }}">
                            <option value="">Select level</option>
                            @foreach ([
                                'Primary' => 'Primary Education',
                                'Secondary' => 'Secondary Education',
                                'Certificate' => 'Certificate',
                                'Diploma' => 'Diploma',
                                'Bachelor Degree' => 'Bachelor Degree',
                                'Masters Degree' => 'Masters Degree',
                                'PhD' => 'PhD',
                                'Other' => 'Other',
                            ] as $value => $label)
                                <option value="{{ $value }}" {{ $val('education_level') == $value ? 'selected' : '' }}>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    @error('education_level')<span class="err"><i class="bi bi-exclamation-circle"></i>{{ $message }}</span>@enderror
                </div>
            </div>

            @foreach ([
                'primary' => ['Primary school', 'bi-backpack', 'e.g. Mlimani Primary School', '2005'],
                'secondary' => ['Secondary school', 'bi-book', 'e.g. Azania Secondary School', '2009'],
            ] as $lvl => [$title, $icon, $ph, $yr])
                <div class="group-title"><i class="bi {{ $icon }}"></i> {{ $title }}</div>
                <div class="fgrid">
                    <div class="field">
                        <label for="{{ $lvl }}_school">School name</label>
                        <div class="control"><i class="bi bi-building"></i>
                            <input type="text" name="{{ $lvl }}_school" id="{{ $lvl }}_school" value="{{ $val($lvl . '_school') }}" class="{{ $inv($lvl . '_school') }}" placeholder="{{ $ph }}" maxlength="255">
                        </div>
                        @error($lvl . '_school')<span class="err"><i class="bi bi-exclamation-circle"></i>{{ $message }}</span>@enderror
                    </div>
                    <div class="field">
                        <label for="{{ $lvl }}_completion_year">Completion year</label>
                        <div class="control"><i class="bi bi-calendar2-check"></i>
                            <input type="number" name="{{ $lvl }}_completion_year" id="{{ $lvl }}_completion_year" value="{{ $val($lvl . '_completion_year') }}" class="{{ $inv($lvl . '_completion_year') }}" min="1950" max="{{ date('Y') }}" placeholder="e.g. {{ $yr }}">
                        </div>
                        @error($lvl . '_completion_year')<span class="err"><i class="bi bi-exclamation-circle"></i>{{ $message }}</span>@enderror
                    </div>
                </div>
            @endforeach

            <div class="group-title"><i class="bi bi-bank"></i> College / University</div>
            <div class="fgrid three">
                <div class="field">
                    <label for="higher_institution">Institution</label>
                    <div class="control"><i class="bi bi-bank2"></i>
                        <input type="text" name="higher_institution" id="higher_institution" value="{{ $val('higher_institution') }}" class="{{ $inv('higher_institution') }}" placeholder="e.g. University of Dodoma" maxlength="255">
                    </div>
                    @error('higher_institution')<span class="err"><i class="bi bi-exclamation-circle"></i>{{ $message }}</span>@enderror
                </div>
                <div class="field">
                    <label for="course">Course studied</label>
                    <div class="control"><i class="bi bi-journal-text"></i>
                        <input type="text" name="course" id="course" value="{{ $val('course') }}" class="{{ $inv('course') }}" placeholder="e.g. Computer Science" maxlength="255">
                    </div>
                    @error('course')<span class="err"><i class="bi bi-exclamation-circle"></i>{{ $message }}</span>@enderror
                </div>
                <div class="field">
                    <label for="qualification">Qualification</label>
                    <div class="control"><i class="bi bi-award"></i>
                        <input type="text" name="qualification" id="qualification" value="{{ $val('qualification') }}" class="{{ $inv('qualification') }}" placeholder="e.g. BSc. IT" maxlength="255">
                    </div>
                    @error('qualification')<span class="err"><i class="bi bi-exclamation-circle"></i>{{ $message }}</span>@enderror
                </div>
            </div>
        </section>

        {{-- STEP 5: REVIEW --}}
        <section class="panel" data-step="5">
            <div class="panel-head">
                <span class="panel-ico"><i class="bi bi-patch-check"></i></span>
                <div><small>Step 5</small><h3>Review & submit</h3></div>
            </div>

            <div class="review" id="review"></div>

            <label class="confirm">
                <input type="checkbox" id="confirmCheck">
                <span>{{ $editing ? 'I confirm that these changes are accurate and supported by documentation.' : 'I confirm that the information above is accurate and has been verified against the applicant\'s supporting documents.' }}</span>
            </label>
        </section>

        {{-- FOOTER --}}
        <div class="wiz-foot">
            <a href="{{ $editing ? route('people.show', $person) : route('dashboard') }}" class="btn-w btn-ghost" id="cancelBtn"><i class="bi bi-x-lg"></i> Cancel</a>
            <button type="button" class="btn-w btn-ghost hidden" id="prevBtn"><i class="bi bi-arrow-left"></i> Back</button>

            <div class="foot-right">
                <button type="button" class="btn-w btn-primary" id="nextBtn">Continue <i class="bi bi-arrow-right"></i></button>
                <button type="submit" class="btn-w btn-primary hidden" id="submitBtn" disabled>
                    @if ($editing)<i class="bi bi-check2-circle"></i> Save changes @else<i class="bi bi-fingerprint"></i> Register & generate NIN @endif
                </button>
            </div>
        </div>
    </form>
</div>

@include('partials.camera')
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {

    const $ = (id) => document.getElementById(id);
    const escape = (s) => String(s ?? '').replace(/[&<>"']/g, (c) => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));

    // ==========================================
    // 1. WIZARD NAVIGATION
    // ==========================================

    const form = $('regForm');
    const panels = [...document.querySelectorAll('.panel')];
    const stepBtns = [...document.querySelectorAll('.step-btn')];
    const TOTAL = panels.length;
    let current = parseInt($('wizard').dataset.start, 10) || 1;
    const visited = new Set([1]);

    function panel(n) { return panels[n - 1]; }

    // Kagua sehemu zote za hatua; onyesha kosa la kwanza.
    function validateStep(n) {
        const fields = [...panel(n).querySelectorAll('input, select')].filter((el) => !el.disabled && el.type !== 'file');
        panel(n).classList.add('was-checked');

        for (const el of fields) {
            if (!el.checkValidity()) {
                el.reportValidity();
                el.focus({ preventScroll: false });
                return false;
            }
        }
        return true;
    }

    function show(n) {
        current = n;
        visited.add(n);

        panels.forEach((p, i) => p.classList.toggle('active', i + 1 === n));
        stepBtns.forEach((b, i) => {
            b.classList.toggle('active', i + 1 === n);
            b.classList.toggle('done', i + 1 < n || (visited.has(i + 1) && i + 1 !== n && i + 1 < TOTAL));
        });

        const pct = Math.round(n / TOTAL * 100);
        $('progressBar').style.width = pct + '%';
        $('progressText').textContent = 'Step ' + n + ' of ' + TOTAL;
        $('progressPct').textContent = pct + '%';

        $('prevBtn').classList.toggle('hidden', n === 1);
        $('cancelBtn').classList.toggle('hidden', n !== 1);
        $('nextBtn').classList.toggle('hidden', n === TOTAL);
        $('submitBtn').classList.toggle('hidden', n !== TOTAL);

        if (n === TOTAL) buildReview();

        window.scrollTo({ top: 0, behavior: 'smooth' });
    }

    $('nextBtn').addEventListener('click', () => { if (validateStep(current)) show(current + 1); });
    $('prevBtn').addEventListener('click', () => show(current - 1));

    stepBtns.forEach((btn) => btn.addEventListener('click', function () {
        const target = parseInt(this.dataset.goto, 10);
        if (target <= current) return show(target);

        // Kwenda mbele: hatua zote za katikati lazima ziwe sahihi.
        for (let s = current; s < target; s++) {
            if (!validateStep(s)) { if (s !== current) show(s); return; }
        }
        show(target);
    }));

    // Enter haiwasilishi fomu kabla ya hatua ya mwisho.
    form.addEventListener('keydown', function (e) {
        if (e.key === 'Enter' && e.target.tagName === 'INPUT' && current < TOTAL) {
            e.preventDefault();
            $('nextBtn').click();
        }
    });

    form.addEventListener('submit', function (e) {
        for (let s = 1; s < TOTAL; s++) {
            if (!validateStep(s)) { e.preventDefault(); show(s); validateStep(s); return; }
        }
        $('submitBtn').disabled = true;
        $('submitBtn').innerHTML = '<i class="bi bi-hourglass-split"></i> Registering…';
    });

    $('confirmCheck').addEventListener('change', function () { $('submitBtn').disabled = !this.checked; });

    // ==========================================
    // 2. AGE + NIN PREVIEW
    // ==========================================

    const dob = $('date_of_birth');

    function updateDob() {
        if (!dob.value) {
            $('ageHelp').textContent = '';
            if ($('ninPreview')) $('ninPreview').textContent = 'YYYYMMDD ············';
            return;
        }
        const d = new Date(dob.value);
        const now = new Date();
        let age = now.getFullYear() - d.getFullYear();
        if (now < new Date(now.getFullYear(), d.getMonth(), d.getDate())) age--;

        $('ageHelp').textContent = age >= 0 ? age + ' years old' : '';
        if ($('ninPreview')) $('ninPreview').textContent = dob.value.replaceAll('-', '') + ' ············';
    }

    dob.addEventListener('input', updateDob);
    updateDob();

    // ==========================================
    // 3. PROFILE PICTURE (click + drag & drop)
    // ==========================================

    const input = $('profile_picture');
    const preview = $('photoPreview');
    const placeholder = $('photoPlaceholder');
    const dropzone = $('dropzone');
    let currentImageUrl = null;

    function clearPhoto() {
        input.value = '';
        preview.src = '';
        preview.style.display = 'none';
        placeholder.style.display = '';
        dropzone.classList.remove('has-image');
    }

    function handleFile(file) {
        if (!file) return clearPhoto();

        if (!['image/jpeg', 'image/png'].includes(file.type)) {
            alert('Please select a JPG, JPEG or PNG image.');
            return clearPhoto();
        }
        if (file.size > 2 * 1024 * 1024) {
            alert('Image size must not exceed 2MB.');
            return clearPhoto();
        }

        if (currentImageUrl) URL.revokeObjectURL(currentImageUrl);
        currentImageUrl = URL.createObjectURL(file);

        preview.src = currentImageUrl;
        preview.style.display = 'block';
        placeholder.style.display = 'none';
        dropzone.classList.add('has-image');
    }

    input.addEventListener('change', function () { handleFile(this.files[0]); });

    ['dragenter', 'dragover'].forEach((ev) => dropzone.addEventListener(ev, (e) => {
        e.preventDefault();
        dropzone.classList.add('drag');
    }));
    ['dragleave', 'drop'].forEach((ev) => dropzone.addEventListener(ev, (e) => {
        e.preventDefault();
        dropzone.classList.remove('drag');
    }));
    dropzone.addEventListener('drop', function (e) {
        const file = e.dataTransfer.files[0];
        if (!file) return;
        const dt = new DataTransfer();
        dt.items.add(file);
        input.files = dt.files;
        handleFile(file);
    });

    // Piga picha kwa kamera (webcam au kamera ya simu).
    $('photoCamera').addEventListener('click', function (e) {
        e.preventDefault();
        e.stopPropagation();
        NinCamera.capture().then(function (file) {
            if (!file) return;
            const dt = new DataTransfer();
            dt.items.add(file);
            input.files = dt.files;
            handleFile(file);
        });
    });

    $('photoRemove').addEventListener('click', function (e) {
        e.preventDefault();
        e.stopPropagation();
        clearPhoto();
    });

    // ==========================================
    // 4. LOCATION DROPDOWNS
    // ==========================================

    function resetSelect(select, text) {
        select.innerHTML = '';
        const option = document.createElement('option');
        option.value = '';
        option.textContent = text;
        select.appendChild(option);
        select.disabled = true;
    }

    function buildUrl(template, id) {
        return template.replace('__ID__', encodeURIComponent(id));
    }

    async function loadOptions(select, url, text, oldValue = '') {
        resetSelect(select, 'Loading…');

        try {
            const response = await fetch(url, {
                headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
            });

            if (!response.ok) throw new Error('HTTP ' + response.status);

            const items = await response.json();
            resetSelect(select, text);

            items.forEach(function (item) {
                const option = document.createElement('option');
                option.value = item.id;
                option.textContent = item.type ? item.name + ' (' + item.type + ')' : item.name;
                if (String(item.id) === String(oldValue)) option.selected = true;
                select.appendChild(option);
            });

            select.disabled = items.length === 0;
            return items;

        } catch (error) {
            console.error('Location dropdown error:', error);
            resetSelect(select, 'Unable to load data');
            return [];
        }
    }

    // Birth: region -> district
    const birthRegion = $('birth_region_id');
    const birthDistrict = $('birth_district_id');

    async function onBirthRegion(oldDistrict = '') {
        resetSelect(birthDistrict, 'Select district');
        if (!birthRegion.value) return;
        await loadOptions(birthDistrict, buildUrl(birthDistrict.dataset.url, birthRegion.value), 'Select district', oldDistrict);
    }

    birthRegion.addEventListener('change', () => onBirthRegion());
    if (birthRegion.value) onBirthRegion(birthDistrict.dataset.old || '');

    // Residence: region -> district -> ward -> village
    const region = $('region_id');
    const district = $('district_id');
    const ward = $('ward_id');
    const village = $('village_id');
    const villageHelp = $('villageHelp');

    async function onRegion(oldDistrict = '') {
        resetSelect(district, 'Select district');
        resetSelect(ward, 'Select ward');
        resetSelect(village, 'Select village / street');
        villageHelp.textContent = 'Select a ward to see its villages and streets.';
        if (!region.value) return;
        await loadOptions(district, buildUrl(district.dataset.url, region.value), 'Select district', oldDistrict);
    }

    async function onDistrict(oldWard = '') {
        resetSelect(ward, 'Select ward');
        resetSelect(village, 'Select village / street');
        if (!district.value) return;
        await loadOptions(ward, buildUrl(ward.dataset.url, district.value), 'Select ward', oldWard);
    }

    async function onWard(oldVillage = '') {
        resetSelect(village, 'Select village / street');
        if (!ward.value) return;
        const items = await loadOptions(village, buildUrl(village.dataset.url, ward.value), 'Select village / street', oldVillage);
        villageHelp.textContent = items.length
            ? items.length + ' villages / streets available.'
            : 'No village/street data for this ward yet.';
    }

    region.addEventListener('change', () => onRegion());
    district.addEventListener('change', () => onDistrict());
    ward.addEventListener('change', () => onWard());

    (async function restore() {
        if (!region.value) return;
        const oldDistrict = district.dataset.old || '';
        const oldWard = ward.dataset.old || '';
        const oldVillage = village.dataset.old || '';

        await onRegion(oldDistrict);
        if (oldDistrict && district.value === oldDistrict) {
            await onDistrict(oldWard);
            if (oldWard && ward.value === oldWard) await onWard(oldVillage);
        }
    })();

    // ==========================================
    // 5. REVIEW SUMMARY
    // ==========================================

    function val(id) {
        const el = $(id);
        if (!el) return '';
        if (el.tagName === 'SELECT') return el.value ? el.options[el.selectedIndex].text : '';
        return el.value.trim();
    }

    function card(title, step, rows) {
        const items = rows.map(([label, value]) =>
            '<dt>' + escape(label) + '</dt><dd class="' + (value ? '' : 'empty') + '">' + (value ? escape(value) : '—') + '</dd>'
        ).join('');
        return '<div class="review-card"><h4>' + escape(title) +
            '<button type="button" data-edit="' + step + '"><i class="bi bi-pencil"></i> Edit</button></h4><dl>' + items + '</dl></div>';
    }

    function buildReview() {
        const gender = (document.querySelector('input[name="gender"]:checked') || {}).value || '';
        const name = [val('first_name'), val('middle_name'), val('last_name')].filter(Boolean).join(' ');
        const photo = preview.style.display === 'block'
            ? '<img src="' + preview.src + '" style="display:block">'
            : '<i class="bi bi-person-fill"></i>';

        $('review').innerHTML =
            '<div class="review-person"><span class="photo-frame">' + photo + '</span><span><b>' + escape(name) + '</b>' +
            '<small>' + escape(gender) + ' · ' + escape(val('date_of_birth')) + ' · ' + escape(val('nationality')) + '</small></span></div>' +
            card('Place of birth', 2, [['Region', val('birth_region_id')], ['District', val('birth_district_id')]]) +
            card('Residence', 2, [['Region', val('region_id')], ['District', val('district_id')], ['Ward', val('ward_id')], ['Village / Street', val('village_id')]]) +
            card('Contact', 2, [['Phone', val('phone')], ['Email', val('email')]]) +
            card('Family', 3, [['Father', val('father_name')], ['Mother', val('mother_name')], ['Guardian', val('guardian_name')]]) +
            card('Education', 4, [['Level', val('education_level')], ['Primary', val('primary_school')], ['Secondary', val('secondary_school')], ['Higher', val('higher_institution')], ['Qualification', val('qualification')]]);

        $('review').querySelectorAll('[data-edit]').forEach((b) =>
            b.addEventListener('click', () => show(parseInt(b.dataset.edit, 10)))
        );
    }

    show(current);
});
</script>
@endpush
