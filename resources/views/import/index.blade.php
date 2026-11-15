@extends('layouts.app')

@section('title', 'Import people')
@section('page_title', 'Import people')

@push('styles')
<style>
    .drop { display: flex; flex-direction: column; align-items: center; gap: 8px; padding: 34px 20px; border: 2px dashed #cbd5e1; border-radius: 16px; background: #f8fafc; text-align: center; cursor: pointer; transition: all .15s; }
    .drop:hover, .drop.over { border-color: #16a34a; background: #f0fdf4; }
    .drop i { font-size: 34px; color: #16a34a; }
    .drop b { font-size: 15px; color: var(--ink); }
    .drop span { font-size: 13px; color: var(--muted); }
    .drop .file { margin-top: 6px; font-weight: 700; color: #166534; }
    .cols { display: flex; flex-wrap: wrap; gap: 6px; }
    .cols code { font-size: 11.5px; padding: 3px 8px; border-radius: 6px; background: #f1f5f9; color: #334155; }
    .cols code.req { background: #dcfce7; color: #166534; font-weight: 700; }
    .steps-list { margin: 0; padding-left: 18px; font-size: 13px; color: var(--text); line-height: 1.7; }
</style>
@endpush

@section('content')
<div class="ui-page">
    <div class="ui-head">
        <div>
            <h2>Import people from a spreadsheet</h2>
            <p>Register many people at once from a CSV file. Every row is checked before anything is saved.</p>
        </div>
        <div class="ui-head-actions">
            <a class="ui-btn" href="{{ route('people.import.template') }}"><i class="bi bi-file-earmark-arrow-down"></i> Download template</a>
        </div>
    </div>

    <div class="ui-grid-3">
        <section class="ui-card">
            <div class="ui-card-head">
                <span class="ico"><i class="bi bi-upload"></i></span>
                <div><h3>Upload CSV</h3><p>Up to {{ number_format(\App\Support\PeopleImporter::MAX_ROWS) }} rows, max 2 MB</p></div>
            </div>
            <form method="POST" action="{{ route('people.import.preview') }}" enctype="multipart/form-data" id="importForm">
                @csrf
                <label class="drop" id="drop" for="file">
                    <i class="bi bi-filetype-csv"></i>
                    <b>Drop the CSV file here or click to choose</b>
                    <span>In Excel: File → Save As → "CSV UTF-8 (Comma delimited)"</span>
                    <span class="file" id="fileName"></span>
                </label>
                <input type="file" name="file" id="file" accept=".csv,text/csv" hidden required>
                @error('file')<p class="ui-err" style="margin-top:10px">{{ $message }}</p>@enderror
                <div style="display:flex;justify-content:flex-end;margin-top:14px">
                    <button class="ui-btn primary" id="go" disabled><i class="bi bi-search"></i> Check file</button>
                </div>
            </form>
        </section>

        <section class="ui-card">
            <div class="ui-card-head">
                <span class="ico"><i class="bi bi-list-check"></i></span>
                <div><h3>How it works</h3><p>Nothing is saved until you confirm</p></div>
            </div>
            <ol class="steps-list">
                <li>Download the template and fill one person per row.</li>
                <li>Upload it: each row is checked (dates, gender, locations, possible duplicates).</li>
                <li>Review the result and confirm. A NIN is generated for every imported person.</li>
            </ol>
            <p style="margin:14px 0 8px;font-size:12.5px;color:var(--muted)">Columns (green = required):</p>
            <div class="cols">
                @foreach ($columns as $c)<code class="{{ in_array($c, $required) ? 'req' : '' }}">{{ $c }}</code>@endforeach
            </div>
            <p style="margin:12px 0 0;font-size:12.5px;color:var(--muted);line-height:1.6">
                Dates: <b>YYYY-MM-DD</b> or <b>DD/MM/YYYY</b>. Gender: Male/Female (M/F, Me/Ke). Locations are typed by name, e.g. region "Dar es Salaam", district "Ilala", and must exist in the official list.
            </p>
        </section>
    </div>
</div>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', () => {
    const input = document.getElementById('file');
    const drop = document.getElementById('drop');
    const show = () => {
        const f = input.files[0];
        document.getElementById('fileName').textContent = f ? f.name : '';
        document.getElementById('go').disabled = !f;
    };
    input.addEventListener('change', show);
    ['dragenter', 'dragover'].forEach((e) => drop.addEventListener(e, (ev) => { ev.preventDefault(); drop.classList.add('over'); }));
    ['dragleave', 'drop'].forEach((e) => drop.addEventListener(e, (ev) => { ev.preventDefault(); drop.classList.remove('over'); }));
    drop.addEventListener('drop', (ev) => {
        if (!ev.dataTransfer.files[0]) return;
        const dt = new DataTransfer();
        dt.items.add(ev.dataTransfer.files[0]);
        input.files = dt.files;
        show();
    });
    document.getElementById('importForm').addEventListener('submit', () => {
        const b = document.getElementById('go');
        b.disabled = true;
        b.innerHTML = '<i class="bi bi-hourglass-split"></i> Checking…';
    });
});
</script>
@endpush
