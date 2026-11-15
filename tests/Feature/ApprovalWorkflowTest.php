<?php

namespace Tests\Feature;

use App\Http\Controllers\CardVerificationController;
use App\Models\Person;
use App\Models\User;
use App\Notifications\ReviewUpdate;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ApprovalWorkflowTest extends TestCase
{
    use RefreshDatabase;

    private User $registrar;
    private User $approver;

    protected function setUp(): void
    {
        parent::setUp();
        config(['services.nin.card_verify_url' => null, 'services.nin.allow_self_approval' => false]);

        $this->registrar = $this->user('Registrar');
        $this->approver = $this->user('Approver');
    }

    private function register(array $form = [], string $action = 'submit'): Person
    {
        $this->actingAs($this->registrar)->post('/people', $this->personForm($form) + ['action' => $action])->assertRedirect();

        return Person::anyStage()->latest('id')->firstOrFail();
    }

    private function approve(Person $person, ?User $by = null)
    {
        return $this->actingAs($by ?? $this->approver)->post("/people/{$person->id}/approve", ['checks' => array_keys(Person::CHECKLIST)]);
    }

    public function test_full_path_draft_submit_review_approve(): void
    {
        $person = $this->register([], 'draft');
        $this->assertSame(Person::DRAFT, $person->stage);
        $this->assertNull($person->submitted_at);

        // Rasimu haionekani kwenye rejista wala kwa utafutaji wa NIN.
        $this->actingAs($this->registrar)->get('/people')->assertDontSee('Amina Juma Mrisho');
        $this->assertSame(0, Person::count());

        $this->actingAs($this->registrar)->post("/people/{$person->id}/submit")->assertRedirect();
        $this->assertSame(Person::SUBMITTED, $person->fresh()->stage);

        $this->actingAs($this->approver)->get('/approvals')->assertOk()->assertSee('Amina Juma Mrisho');
        $this->actingAs($this->approver)->post("/people/{$person->id}/review")->assertRedirect();
        $this->assertSame(Person::IN_REVIEW, $person->fresh()->stage);
        $this->assertSame($this->approver->id, $person->fresh()->reviewer_id);

        $this->approve($person)->assertRedirect()->assertSessionHas('success');
        $person->refresh();

        $this->assertSame(Person::APPROVED, $person->stage);
        $this->assertMatchesRegularExpression('/^19950412\d{12}$/', $person->nin);
        $this->assertNotNull($person->reviewed_at);
        $this->assertSame(1, Person::count());
        $this->assertSame(['submitted', 'claimed', 'approved'], $person->reviews()->pluck('action')->all());
        $this->assertDatabaseHas('activity_logs', ['action' => 'approval.approved', 'subject_id' => $person->id]);

        // Kengele kwa aliyesajili.
        $note = $this->registrar->notifications()->sole();
        $this->assertSame(ReviewUpdate::class, $note->type);
        $this->assertSame('approved', $note->data['action']);
        $this->actingAs($this->registrar)->get("/notifications/{$note->id}")->assertRedirect("/people/{$person->id}");
        $this->assertNotNull($note->fresh()->read_at);
    }

    public function test_nobody_approves_their_own_registration(): void
    {
        $admin = $this->user('Administrator');
        $this->actingAs($admin)->post('/people', $this->personForm() + ['action' => 'submit']);
        $person = Person::anyStage()->sole();

        $this->actingAs($admin)->post("/people/{$person->id}/review")->assertForbidden();
        $this->approve($person, $admin)->assertForbidden();
        $this->assertNull($person->fresh()->nin);

        // Ofisi yenye mtumiaji mmoja inaweza kuruhusu kwa APPROVAL_ALLOW_SELF=true.
        config(['services.nin.allow_self_approval' => true]);
        $this->approve($person, $admin)->assertRedirect();
        $this->assertSame(Person::APPROVED, $person->fresh()->stage);
    }

    public function test_checklist_must_be_complete(): void
    {
        $person = $this->register();

        $this->actingAs($this->approver)
            ->post("/people/{$person->id}/approve", ['checks' => ['photo', 'signature']])
            ->assertSessionHasErrorsIn('review', 'checks');

        $this->assertSame(Person::SUBMITTED, $person->fresh()->stage);
    }

    public function test_return_for_correction_then_resubmit(): void
    {
        $person = $this->register();

        // Imefungwa wakati inasubiri.
        $this->actingAs($this->registrar)->get("/people/{$person->id}/edit")->assertForbidden();

        $this->actingAs($this->approver)->post("/people/{$person->id}/return", ['note' => ''])->assertSessionHasErrorsIn('review', 'note');
        $this->actingAs($this->approver)->post("/people/{$person->id}/return", ['note' => 'Photo is blurred, take a new one.'])->assertRedirect();

        $person->refresh();
        $this->assertSame(Person::RETURNED, $person->stage);
        $this->assertSame('Photo is blurred, take a new one.', $person->review_note);
        $this->assertSame('returned', $this->registrar->notifications()->sole()->data['action']);

        $this->actingAs($this->registrar)->get('/my-submissions')->assertOk()->assertSee('Photo is blurred');
        $this->actingAs($this->registrar)->get("/people/{$person->id}")->assertOk()->assertSee('What needs to be corrected')->assertSee('Resubmit for approval');
        $this->actingAs($this->registrar)->get("/people/{$person->id}/edit")->assertOk()->assertSee('Photo is blurred');

        $this->actingAs($this->registrar)
            ->put("/people/{$person->id}", $this->personForm(['phone' => '0713000000', 'status' => 'Active', 'action' => 'submit']))
            ->assertRedirect();

        $person->refresh();
        $this->assertSame(Person::SUBMITTED, $person->stage);
        $this->assertNull($person->review_note);
        $this->assertSame('0713000000', $person->phone);
        $this->assertSame('resubmitted', $person->reviews()->get()->last()->action);
    }

    public function test_rejected_records_are_final(): void
    {
        $person = $this->register();
        $this->actingAs($this->approver)->post("/people/{$person->id}/reject", ['note' => 'Duplicate of an existing record.']);

        $this->assertSame(Person::REJECTED, $person->fresh()->stage);
        $this->actingAs($this->registrar)->get("/people/{$person->id}/edit")->assertForbidden();
        $this->actingAs($this->registrar)->post("/people/{$person->id}/submit")->assertForbidden();
        $this->approve($person)->assertForbidden();
    }

    public function test_sensitive_change_on_approved_record_needs_re_approval(): void
    {
        $person = $this->register();
        $this->approve($person);
        $nin = $person->fresh()->nin;

        // Simu: hakuna idhini tena.
        $this->actingAs($this->registrar)->put("/people/{$person->id}", $this->personForm(['phone' => '0713999999', 'status' => 'Active']));
        $this->assertSame(Person::APPROVED, $person->fresh()->stage);

        // Jina: inarudi kwenye foleni, NIN inabaki ileile.
        $this->actingAs($this->registrar)->put("/people/{$person->id}", $this->personForm(['first_name' => 'Aminata', 'phone' => '0713999999', 'status' => 'Active']));
        $person->refresh();
        $this->assertSame(Person::SUBMITTED, $person->stage);
        $this->assertSame($nin, $person->nin);
        $this->assertSame('Changed: first name', $person->reviews()->get()->last()->note);

        // Kadi na QR hazifanyi kazi hadi iidhinishwe tena.
        $this->actingAs($this->registrar)->get("/people/{$person->id}/card")->assertNotFound();
        $url = CardVerificationController::urlFor($person);
        $this->get(parse_url($url, PHP_URL_PATH) . '?' . parse_url($url, PHP_URL_QUERY))->assertForbidden();

        $this->approve($person);
        $this->assertSame($nin, $person->fresh()->nin);
        $this->actingAs($this->registrar)->get("/people/{$person->id}/card")->assertOk();
    }

    public function test_a_claimed_record_belongs_to_its_reviewer_until_released(): void
    {
        $person = $this->register();
        $other = $this->user('Approver');
        $admin = $this->user('Administrator');

        $this->actingAs($this->approver)->post("/people/{$person->id}/review");

        $this->approve($person, $other)->assertForbidden();
        $this->actingAs($other)->post("/people/{$person->id}/release")->assertForbidden();

        $this->actingAs($admin)->post("/people/{$person->id}/release")->assertRedirect();
        $this->assertSame(Person::SUBMITTED, $person->fresh()->stage);

        $this->approve($person, $other)->assertRedirect();
        $this->assertSame(Person::APPROVED, $person->fresh()->stage);
    }

    public function test_bulk_approval_skips_own_registrations(): void
    {
        $mine = Person::factory()->create(['stage' => Person::SUBMITTED, 'nin' => null, 'created_by' => $this->approver->id]);
        $a = $this->register(['first_name' => 'Rehema', 'phone' => '0711000001']);
        $b = $this->register(['first_name' => 'Zawadi', 'phone' => '0711000002', 'date_of_birth' => '1990-01-01']);

        $this->actingAs($this->approver)->post('/approvals/bulk', ['ids' => [$mine->id, $a->id, $b->id]])
            ->assertSessionHasErrors('confirm_checked');

        $this->actingAs($this->approver)->post('/approvals/bulk', ['ids' => [$mine->id, $a->id, $b->id], 'confirm_checked' => 1])
            ->assertSessionHas('success', fn ($m) => str_contains($m, 'Approved 2') && str_contains($m, 'Skipped 1'));

        $this->assertNotNull($a->fresh()->nin);
        $this->assertNotNull($b->fresh()->nin);
        $this->assertSame(Person::SUBMITTED, $mine->fresh()->stage);
    }

    public function test_roles(): void
    {
        $person = $this->register();
        $viewer = $this->user('Viewer');

        foreach (['/approvals', '/my-submissions'] as $uri) {
            $this->actingAs($viewer)->get($uri)->assertRedirect(route('dashboard'));
        }
        $this->actingAs($this->registrar)->get('/approvals')->assertRedirect(route('dashboard'));
        $this->approve($person, $this->registrar)->assertRedirect(route('dashboard'));
        $this->actingAs($this->approver)->get('/people/create')->assertRedirect(route('dashboard'));
        $this->assertSame(Person::SUBMITTED, $person->fresh()->stage);
    }

    public function test_pages_render_at_every_stage(): void
    {
        $admin = $this->user('Administrator');
        $stages = [Person::DRAFT, Person::SUBMITTED, Person::IN_REVIEW, Person::RETURNED, Person::REJECTED, Person::APPROVED];

        foreach ($stages as $stage) {
            Person::factory()->create([
                'stage' => $stage,
                'nin' => $stage === Person::APPROVED ? Person::generateNin('1990-01-01') : null,
                'created_by' => $this->registrar->id,
                'submitted_by' => $stage === Person::DRAFT ? null : $this->registrar->id,
                'submitted_at' => $stage === Person::DRAFT ? null : now()->subDays(4),
                'reviewer_id' => in_array($stage, [Person::DRAFT, Person::SUBMITTED], true) ? null : $this->approver->id,
                'reviewed_at' => in_array($stage, [Person::RETURNED, Person::REJECTED, Person::APPROVED], true) ? now() : null,
                'review_note' => in_array($stage, [Person::RETURNED, Person::REJECTED], true) ? 'Some reason' : null,
            ]);
        }

        foreach (Person::anyStage()->get() as $p) {
            foreach ([$this->registrar, $this->approver, $admin, $this->user('Viewer')] as $u) {
                $res = $this->actingAs($u)->get("/people/{$p->id}")->assertOk();
                // Rekodi za zamani zilizoidhinishwa (bila historia ya idhini) hazionyeshi sehemu ya idhini.
                $p->isApproved() ? $res->assertDontSee('id="review"', false) : $res->assertSee($p->stageLabel());
            }
        }

        foreach (['pending', 'mine', 'returned', 'rejected', 'approved'] as $tab) {
            $this->actingAs($this->approver)->get("/approvals?tab={$tab}")->assertOk();
        }
        $this->actingAs($this->registrar)->get('/my-submissions')->assertOk()->assertSee('Some reason');
        $this->actingAs($this->registrar)->get('/dashboard')->assertOk()->assertSee('My registrations');
        $this->actingAs($this->approver)->get('/dashboard')->assertOk()->assertSee('Approval workflow');
    }
}
