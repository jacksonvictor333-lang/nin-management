<?php

namespace Tests\Feature;

use App\Http\Controllers\CardVerificationController;
use App\Models\Person;
use App\Models\User;
use App\Support\Sms;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Nyaraka, namba ya ombi na ukurasa wa hali, SMS, mzunguko wa kadi,
 * kusitisha NIN, ripoti ya utendaji, password imara na session.
 */
class OperationsTest extends TestCase
{
    use RefreshDatabase;

    private User $registrar;
    private User $approver;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        config(['services.nin.card_verify_url' => null, 'services.sms.driver' => 'log']);

        $this->registrar = $this->user('Registrar');
        $this->approver = $this->user('Approver');
    }

    private function registerAndApprove(array $form = []): Person
    {
        $this->actingAs($this->registrar)->post('/people', $this->personForm($form) + ['action' => 'submit']);
        $person = Person::anyStage()->latest('id')->firstOrFail();
        $this->actingAs($this->approver)->post("/people/{$person->id}/approve", ['checks' => array_keys(Person::CHECKLIST)]);

        return $person->fresh();
    }

    private function path(string $url): string
    {
        return parse_url($url, PHP_URL_PATH) . '?' . parse_url($url, PHP_URL_QUERY);
    }

    public function test_documents_upload_with_registration_and_stay_private(): void
    {
        $this->actingAs($this->registrar)->post('/people', $this->personForm() + [
            'action' => 'draft',
            'document_type' => 'birth_certificate',
            'documents' => [UploadedFile::fake()->create('cheti.pdf', 120, 'application/pdf')],
        ])->assertSessionHasNoErrors();

        $person = Person::anyStage()->sole();
        $doc = $person->documents()->sole();
        $this->assertSame('birth_certificate', $doc->type);
        Storage::disk('local')->assertExists($doc->path);
        $this->assertStringStartsWith("documents/{$person->id}/", $doc->path);

        // Rasimu: anaweza kuongeza; zimefichwa kwa wasioingia.
        $this->actingAs($this->registrar)->post("/people/{$person->id}/documents", [
            'type' => 'local_government_letter',
            'files' => [UploadedFile::fake()->create('barua.jpg', 40, 'image/jpeg')],
        ])->assertRedirect();
        $this->assertSame(2, $person->documents()->count());

        $this->actingAs($this->user('Viewer'))->get("/documents/{$doc->id}")->assertOk()->assertDownload('cheti.pdf');
        auth()->logout();
        $this->get("/documents/{$doc->id}")->assertRedirect('/login');

        // Wrong type of file
        $this->actingAs($this->registrar)->post("/people/{$person->id}/documents", [
            'type' => 'other', 'files' => [UploadedFile::fake()->create('virus.exe', 10)],
        ])->assertSessionHasErrors('files.0');

        // Imefungwa ikiwasilishwa.
        $this->actingAs($this->registrar)->post("/people/{$person->id}/submit");
        $this->actingAs($this->registrar)->post("/people/{$person->id}/documents", [
            'type' => 'other', 'files' => [UploadedFile::fake()->create('x.pdf', 5, 'application/pdf')],
        ])->assertForbidden();

        $this->actingAs($this->approver)->post("/people/{$person->id}/review");
        $this->actingAs($this->approver)->get("/people/{$person->id}")->assertSee('2 attached')->assertSee('Supporting documents are attached');
    }

    public function test_application_number_and_public_status_page(): void
    {
        $this->actingAs($this->registrar)->post('/people', $this->personForm() + ['action' => 'submit']);
        $person = Person::anyStage()->sole();
        $this->assertSame(sprintf('APP-%s-%06d', now()->format('Y'), $person->id), $person->reference);
        auth()->logout();

        $this->get('/status')->assertOk()->assertSee('Application status');

        $this->post('/status', ['reference' => $person->reference, 'date_of_birth' => '2000-01-01'])
            ->assertSessionHasErrors('reference');

        $this->post('/status', ['reference' => strtolower($person->reference), 'date_of_birth' => '1995-04-12'])
            ->assertOk()->assertSee('Under review')->assertDontSee($person->phone);

        $this->actingAs($this->approver)->post("/people/{$person->id}/approve", ['checks' => array_keys(Person::CHECKLIST)]);
        auth()->logout();
        $this->post('/status', ['reference' => $person->reference, 'date_of_birth' => '1995-04-12'])
            ->assertOk()->assertSee('Approved')->assertDontSee($person->fresh()->nin);
    }

    public function test_sms_is_sent_at_each_step(): void
    {
        $this->assertSame('255754123456', Sms::normalize('0754 123 456'));
        $this->assertSame('255754123456', Sms::normalize('+255754123456'));
        $this->assertSame('255754123456', Sms::normalize('754123456'));
        $this->assertNull(Sms::normalize('12345'));

        $person = $this->registerAndApprove();

        $sms = \App\Models\ActivityLog::where('action', 'sms.sent')->where('subject_id', $person->id)->orderBy('id')->get();
        $this->assertCount(2, $sms);
        $this->assertStringContainsString('+255754123456', $sms[0]->description);
        $this->assertStringContainsString($person->reference, $sms[0]->properties['text']);
        $this->assertStringContainsString($person->nin, $sms[1]->properties['text']);

        config(['services.sms.driver' => 'off']);
        $this->assertFalse(Sms::notifyPerson($person, 'card_ready'));
    }

    public function test_card_lifecycle_and_replacement(): void
    {
        $person = $this->registerAndApprove();
        $oldQr = $this->path(CardVerificationController::urlFor($person));

        // Haiwezi kuruka hatua.
        $this->actingAs($this->registrar)->post("/people/{$person->id}/card-status", ['action' => 'ready'])->assertStatus(422);

        $this->actingAs($this->registrar)->post("/people/{$person->id}/card-status", ['action' => 'printed'])->assertRedirect();
        $this->actingAs($this->registrar)->post('/cards/bulk', ['action' => 'ready', 'ids' => [$person->id]])->assertSessionHas('success');
        $this->assertSame('ready', $person->fresh()->card_status);
        $this->assertDatabaseHas('activity_logs', ['action' => 'sms.sent', 'subject_id' => $person->id, 'description' => 'SMS (card_ready) to +255754123456 [log only]']);

        $this->actingAs($this->registrar)->get('/cards?tab=ready')->assertOk()->assertSee($person->full_name);

        $this->actingAs($this->registrar)->post("/people/{$person->id}/card-status", ['action' => 'collected'])->assertSessionHasErrors('collected_by');
        $this->actingAs($this->registrar)->post("/people/{$person->id}/card-status", ['action' => 'collected', 'collected_by' => 'Amina Mrisho'])->assertRedirect();
        $person->refresh();
        $this->assertSame('collected', $person->card_status);
        $this->assertSame('Amina Mrisho', $person->card_collected_by);

        // Kadi imepotea: toleo #2, QR ya zamani inakataliwa.
        $this->get($oldQr)->assertOk()->assertSee('Valid card');
        $this->actingAs($this->registrar)->post("/people/{$person->id}/card-replace", ['reason' => 'lost', 'note' => 'Police RB/123'])->assertRedirect();
        $person->refresh();
        $this->assertSame(2, $person->card_version);
        $this->assertNull($person->card_status);
        $this->assertSame(['printed', 'ready', 'collected', 'lost'], $person->cardEvents()->pluck('event')->all());

        $this->get($oldQr)->assertOk()->assertSee('Card no longer valid');
        $this->get($this->path(CardVerificationController::urlFor($person)))->assertOk()->assertSee('Valid card');
    }

    public function test_deactivation_for_a_deceased_holder(): void
    {
        $person = $this->registerAndApprove();
        $admin = $this->user('Administrator');
        $qr = $this->path(CardVerificationController::urlFor($person));

        $this->actingAs($this->registrar)->post("/people/{$person->id}/deactivate", ['reason' => 'deceased'])->assertRedirect(route('dashboard'));

        $this->actingAs($admin)->post("/people/{$person->id}/deactivate", ['reason' => 'deceased', 'note' => 'Death cert 998877'])
            ->assertSessionHasErrorsIn('deactivate', 'date_of_death');

        $this->actingAs($admin)->post("/people/{$person->id}/deactivate", ['reason' => 'deceased', 'date_of_death' => '2026-01-10', 'note' => 'Death cert 998877'])
            ->assertRedirect();

        $person->refresh();
        $this->assertSame('Inactive', $person->status);
        $this->assertSame('2026-01-10', $person->date_of_death->toDateString());
        $this->actingAs($admin)->get("/people/{$person->id}")->assertSee('NIN deactivated — Deceased')->assertSee('Death cert 998877');
        $this->get($qr)->assertSee('NIN deactivated');
        $this->actingAs($this->registrar)->get("/people/{$person->id}/card")->assertNotFound();
        $this->actingAs($this->registrar)->post("/people/{$person->id}/card-status", ['action' => 'printed'])->assertStatus(422);

        $this->actingAs($admin)->delete("/people/{$person->id}/deactivate", ['note' => 'Entered in error'])->assertRedirect();
        $person->refresh();
        $this->assertSame('Active', $person->status);
        $this->assertNull($person->deactivation_reason);
    }

    public function test_performance_report(): void
    {
        $this->registerAndApprove();
        $this->actingAs($this->registrar)->post('/people', $this->personForm(['first_name' => 'Zawadi', 'phone' => '0711000002', 'date_of_birth' => '1990-01-01']) + ['action' => 'submit']);
        $second = Person::anyStage()->latest('id')->first();
        $this->actingAs($this->approver)->post("/people/{$second->id}/return", ['note' => 'Photo missing please add']);

        $this->actingAs($this->user('Administrator'))->get('/reports/performance?days=30')
            ->assertOk()
            ->assertSee($this->registrar->name)
            ->assertSee($this->approver->name)
            ->assertSee('50%');   // 1 kati ya maamuzi 2 ilikuwa idhini

        $this->actingAs($this->approver)->get('/reports/performance')->assertRedirect(route('dashboard'));
    }

    public function test_strong_passwords_session_and_timezone(): void
    {
        $admin = $this->user('Administrator');

        $this->actingAs($admin)->post('/users', [
            'name' => 'Weak', 'email' => 'weak@example.com', 'role' => 'Viewer', 'password' => 'password1', 'password_confirmation' => 'password1',
        ])->assertSessionHasErrorsIn('create', 'password');

        $this->actingAs($admin)->post('/users', [
            'name' => 'Strong', 'email' => 'strong@example.com', 'role' => 'Viewer', 'password' => 'Test@1234', 'password_confirmation' => 'Test@1234',
        ])->assertSessionHasNoErrors();

        $this->actingAs($admin)->get('/session/ping')->assertNoContent();
        $this->actingAs($admin)->get('/dashboard')->assertSee('id="idleDialog"', false);
        auth()->logout();
        $this->get('/login?expired=1')->assertSee('signed out because the system was not used');

        $this->assertSame('Africa/Dar_es_Salaam', config('app.timezone'));
    }
}
