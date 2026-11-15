{{-- Nyaraka za mwombaji kwenye profile. Vigezo: $person, $canEdit --}}
@use('App\Models\Person')
@php $docs = $person->documents; @endphp

@push('styles')
<style>
    .docs { margin-top: 22px; }
    .doc-list { display: grid; grid-template-columns: repeat(auto-fill, minmax(260px, 1fr)); gap: 10px; }
    .doc { display: flex; gap: 12px; align-items: center; padding: 12px; border: 1px solid var(--border, #e5e7eb); border-radius: 14px; background: #fff; min-width: 0; }
    .doc-ico { width: 44px; height: 44px; border-radius: 11px; display: grid; place-items: center; font-size: 20px; flex-shrink: 0; background: #fef2f2; color: #b91c1c; overflow: hidden; }
    .doc-ico.img { background: #eff6ff; color: #1d4ed8; }
    .doc-ico img { width: 100%; height: 100%; object-fit: cover; }
    .doc-body { min-width: 0; flex: 1; }
    .doc-body b { display: block; font-size: 13.5px; color: #0f172a; }
    .doc-body a.name { display: block; font-size: 12.5px; color: #15803d; text-decoration: none; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
    .doc-body a.name:hover { text-decoration: underline; }
    .doc-body small { display: block; font-size: 11.5px; color: #94a3b8; }
    .doc-actions { display: flex; gap: 4px; }
    .doc-actions form { margin: 0; }
    .doc-actions a, .doc-actions button { width: 32px; height: 32px; border-radius: 9px; display: grid; place-items: center; border: 1px solid #e5e7eb; background: #fff; color: #475569; cursor: pointer; text-decoration: none; }
    .doc-actions a:hover { color: #15803d; border-color: #86efac; }
    .doc-actions button:hover { color: #b91c1c; border-color: #fecaca; }
    .doc-upload { display: flex; gap: 10px; flex-wrap: wrap; align-items: flex-end; margin-top: 14px; padding: 14px; border: 1px dashed #cbd5e1; border-radius: 14px; background: #fcfcfd; }
    .doc-upload .ui-field { flex: 1; min-width: 200px; }
    .doc-upload input[type=file] { font-size: 13px; padding: 8px 10px; height: auto; }
    .doc-none { padding: 18px; border-radius: 14px; background: #fffbeb; color: #92400e; font-size: 13.5px; display: flex; gap: 10px; align-items: center; }
    @media print { .docs { display: none; } }
</style>
@endpush

<section class="pf-card docs no-print" id="documents">
    <div class="pf-card-head">
        <span class="ico"><i class="bi bi-folder2-open"></i></span>
        <div><h3>Supporting documents</h3><p>Birth certificate, local government letter and other evidence — {{ $docs->count() }} attached</p></div>
    </div>

    @if ($docs->isEmpty())
        <div class="doc-none"><i class="bi bi-exclamation-circle"></i> No documents attached yet.{{ $canEdit ? ' Upload them below before submitting for approval.' : '' }}</div>
    @else
        <div class="doc-list">
            @foreach ($docs as $doc)
                <div class="doc">
                    <span class="doc-ico {{ $doc->isImage() ? 'img' : '' }}">
                        @if ($doc->isImage())
                            <img src="{{ route('documents.show', ['document' => $doc, 'inline' => 1]) }}" alt="" loading="lazy">
                        @else
                            <i class="bi bi-file-earmark-pdf"></i>
                        @endif
                    </span>
                    <div class="doc-body">
                        <b>{{ $doc->typeLabel() }}</b>
                        <a class="name" href="{{ route('documents.show', ['document' => $doc, 'inline' => 1]) }}" target="_blank" title="{{ $doc->original_name }}">{{ $doc->original_name }}</a>
                        <small>{{ $doc->sizeForHumans() }} · {{ $doc->created_at?->format('d M Y') }}{{ $doc->uploader ? ' · ' . $doc->uploader->name : '' }}</small>
                    </div>
                    <div class="doc-actions">
                        <a href="{{ route('documents.show', $doc) }}" title="Download"><i class="bi bi-download"></i></a>
                        @if ($canEdit)
                            <form method="POST" action="{{ route('documents.destroy', $doc) }}" onsubmit="return confirm('Remove this document?')">
                                @csrf @method('DELETE')
                                <button title="Remove"><i class="bi bi-trash"></i></button>
                            </form>
                        @endif
                    </div>
                </div>
            @endforeach
        </div>
    @endif

    @if ($canEdit)
        <form class="doc-upload" method="POST" action="{{ route('documents.store', $person) }}" enctype="multipart/form-data">
            @csrf
            <div class="ui-field" style="max-width:240px">
                <label for="docType">Document type</label>
                <select class="ui-select" id="docType" name="type">
                    @foreach (Person::DOCUMENT_TYPES as $k => $label)
                        <option value="{{ $k }}" @selected(old('type') === $k)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div class="ui-field">
                <label for="docFiles">Files <span class="ui-help">— PDF, JPG or PNG, up to 5 MB each</span></label>
                <input class="ui-input" type="file" id="docFiles" name="files[]" accept=".pdf,.jpg,.jpeg,.png,application/pdf,image/*" multiple required>
            </div>
            <button class="ui-btn primary"><i class="bi bi-upload"></i> Upload</button>
            @if ($errors->has('files') || $errors->has('files.*') || $errors->has('type'))
                <div class="ui-err" style="width:100%">{{ $errors->first('files') ?: ($errors->first('files.*') ?: $errors->first('type')) }}</div>
            @endif
        </form>
    @endif
</section>
