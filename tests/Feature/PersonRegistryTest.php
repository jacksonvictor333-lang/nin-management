<?php

namespace Tests\Feature;

use App\Models\ActivityLog;
use App\Models\Person;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PersonRegistryTest extends TestCase
{
    use RefreshDatabase;

    public function test_registration_waits_for_approval_then_gets_a_nin_from_the_birth_date(): void
    {
        ['region' => $r, 'district' => $d, 'ward' => $w, 'village' => $v] = $this->locations();

        $this->actingAs($this->user('Registrar'))
            ->post('/people', $this->personForm([
                'region_id' => $r->id, 'district_id' => $d->id, 'ward_id' => $w->id, 'village_id' => $v->id,
            ]))
            ->assertRedirect();

        $person = Person::anyStage()->sole();
        $this->assertSame('submitted', $person->stage);
        $this->assertNull($person->nin);

        $this->actingAs($this->user('Approver'))->post("/people/{$person->id}/approve", ['checks' => array_keys(Person::CHECKLIST)]);
        $person->refresh();

        $this->assertSame('approved', $person->stage);
        $this->assertMatchesRegularExpression('/^19950412\d{12}$/', $person->nin);
        $this->assertSame('Dar es Salaam', $person->region);
        $this->assertSame('Congo', $person->village);
        $this->assertDatabaseHas('activity_logs', ['action' => 'person.created', 'subject_id' => $person->id]);
    }

    public function test_location_hierarchy_is_enforced(): void
    {
        ['region' => $r, 'ward' => $w] = $this->locations();
        \App\Models\District::create(['id' => 'TZ0703', 'region_id' => 'TZ07', 'name' => 'Temeke']);

        // Kata ya Ilala haiwezi kuwa ndani ya wilaya ya Temeke.
        $this->actingAs($this->user())
            ->post('/people', $this->personForm(['region_id' => $r->id, 'district_id' => 'TZ0703', 'ward_id' => $w->id]))
            ->assertSessionHasErrors('ward_id');

        $this->assertSame(0, Person::anyStage()->count());
    }

    public function test_possible_duplicates_need_confirmation(): void
    {
        $existing = Person::factory()->create(['first_name' => 'Amina', 'last_name' => 'Mrisho', 'date_of_birth' => '1995-04-12']);
        $user = $this->user('Registrar');

        // Tahajia tofauti kidogo, tarehe ileile.
        $this->actingAs($user)->getJson('/people/duplicates/check?first_name=Aminah&last_name=Mrisho&date_of_birth=1995-04-12')
            ->assertOk()
            ->assertJsonPath('matches.0.nin', $existing->nin);

        $this->actingAs($user)->post('/people', $this->personForm(['first_name' => 'Aminah']))
            ->assertSessionHasErrors('confirm_not_duplicate');
        $this->assertSame(1, Person::anyStage()->count());

        $this->actingAs($user)->post('/people', $this->personForm(['first_name' => 'Aminah', 'confirm_not_duplicate' => 1]))
            ->assertRedirect();
        $this->assertSame(2, Person::anyStage()->count());
    }

    public function test_different_people_are_not_flagged(): void
    {
        Person::factory()->create(['first_name' => 'Amina', 'last_name' => 'Mrisho', 'date_of_birth' => '1995-04-12']);

        $this->actingAs($this->user())
            ->post('/people', $this->personForm(['first_name' => 'Halima', 'phone' => '0699000111']))
            ->assertSessionHasNoErrors();

        $this->assertSame(2, Person::anyStage()->count());
    }

    public function test_update_keeps_nin_and_records_old_and_new_values(): void
    {
        $person = Person::factory()->create(['first_name' => 'Charles', 'phone' => '0715000000', 'date_of_birth' => '1991-03-21']);
        $nin = $person->nin;

        $this->actingAs($this->user())
            ->put("/people/{$person->id}", array_merge($this->personForm([
                'first_name' => 'Charlie', 'last_name' => $person->last_name, 'middle_name' => $person->middle_name,
                'date_of_birth' => '1991-03-21', 'gender' => $person->gender, 'phone' => '0713999999',
            ]), ['status' => 'Active', 'nin' => '00000000000000000000']))
            ->assertRedirect();

        $person->refresh();
        $this->assertSame($nin, $person->nin);
        $this->assertSame('Charlie', $person->first_name);

        $changes = collect(ActivityLog::where('action', 'person.updated')->sole()->properties['changes'])->keyBy('field');
        $this->assertSame(['Charles', 'Charlie'], [$changes['first_name']['old'], $changes['first_name']['new']]);
        $this->assertSame(['0715000000', '0713999999'], [$changes['phone']['old'], $changes['phone']['new']]);
    }

    public function test_archive_hides_a_record_and_restore_brings_it_back(): void
    {
        $person = Person::factory()->create();
        $admin = $this->user();

        $this->actingAs($admin)->delete("/people/{$person->id}", ['archive_reason' => ''])->assertSessionHasErrors('archive_reason');

        $this->actingAs($admin)->delete("/people/{$person->id}", ['archive_reason' => 'Duplicate record'])->assertRedirect('/people');
        $this->assertSoftDeleted($person);

        $this->actingAs($admin)->get("/people/{$person->id}")->assertNotFound();
        $this->actingAs($admin)->get("/people/lookup/{$person->nin}")->assertNotFound();
        $this->actingAs($admin)->get('/archive')->assertOk()->assertSee('Duplicate record');

        $this->actingAs($admin)->patch("/archive/{$person->id}/restore")->assertRedirect("/people/{$person->id}");
        $this->assertNotSoftDeleted($person);
        $this->assertNull($person->fresh()->archive_reason);
    }

    public function test_nin_generation_skips_archived_nins(): void
    {
        $archived = Person::factory()->create(['date_of_birth' => '2000-01-01']);
        $archived->delete();

        for ($i = 0; $i < 20; $i++) {
            $this->assertNotSame($archived->nin, Person::generateNin('2000-01-01'));
        }
        $this->assertStringStartsWith('20000101', Person::generateNin('2000-01-01'));
    }
}
