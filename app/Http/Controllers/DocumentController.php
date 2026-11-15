<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\Person;
use App\Models\PersonDocument;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

/**
 * Nyaraka za mwombaji. Zinahifadhiwa kwenye storage/app/private (si za umma)
 * na zinafunguliwa na watumiaji walioingia tu.
 */
class DocumentController extends Controller
{
    public const MAX_KB = 5120;
    public const MIMES = 'pdf,jpg,jpeg,png';

    public function store(Request $request, Person $person)
    {
        abort_unless($person->canBeEditedBy($request->user()), 403, 'Documents cannot be changed while the record is waiting for approval.');

        $data = $request->validate([
            'type' => ['required', Rule::in(array_keys(Person::DOCUMENT_TYPES))],
            'files' => ['required', 'array', 'max:5'],
            'files.*' => ['file', 'mimes:' . self::MIMES, 'max:' . self::MAX_KB],
        ], ['files.*.max' => 'Each document must be 5 MB or smaller.', 'files.*.mimes' => 'Documents must be PDF, JPG or PNG.']);

        foreach ($data['files'] as $file) {
            self::save($person, $file, $data['type']);
        }

        return redirect()->to(route('people.show', $person) . '#documents')
            ->with('success', count($data['files']) === 1 ? 'Document uploaded.' : count($data['files']) . ' documents uploaded.');
    }

    /** Hifadhi faili moja (pia inatumika na fomu ya usajili). */
    public static function save(Person $person, UploadedFile $file, string $type): PersonDocument
    {
        $doc = $person->documents()->create([
            'type' => $type,
            'original_name' => mb_substr($file->getClientOriginalName(), 0, 200),
            'path' => $file->store("documents/{$person->id}", 'local'),
            'mime' => $file->getMimeType(),
            'size' => $file->getSize(),
            'uploaded_by' => auth()->id(),
        ]);

        ActivityLog::record('document.uploaded', "Uploaded {$doc->typeLabel()} for {$person->full_name}", $person, ['file' => $doc->original_name]);

        return $doc;
    }

    public function show(Request $request, PersonDocument $document)
    {
        abort_unless(Storage::disk('local')->exists($document->path), 404);

        $inline = $request->boolean('inline') && ($document->isImage() || $document->mime === 'application/pdf');

        return Storage::disk('local')->response($document->path, $document->original_name, [
            'Content-Type' => $document->mime ?: 'application/octet-stream',
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, no-store',
        ], $inline ? 'inline' : 'attachment');
    }

    public function destroy(Request $request, PersonDocument $document)
    {
        $person = $document->person;
        abort_unless($person && $person->canBeEditedBy($request->user()), 403);

        Storage::disk('local')->delete($document->path);
        $document->delete();

        ActivityLog::record('document.removed', "Removed {$document->typeLabel()} ({$document->original_name}) from {$person->full_name}", $person);

        return redirect()->to(route('people.show', $person) . '#documents')->with('success', 'Document removed.');
    }
}
