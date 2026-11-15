<?php

namespace Tests\Feature;

use App\Http\Controllers\CardVerificationController;
use App\Models\Person;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class IdCardTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // Anwani ya simu (CARD_VERIFY_URL) kutoka .env haitumiki kwenye majaribio.
        config(['services.nin.card_verify_url' => null]);
    }

    public function test_qr_link_verifies_a_card_without_leaking_private_details(): void
    {
        $person = Person::factory()->create(['first_name' => 'Neema', 'last_name' => 'Mushi', 'date_of_birth' => '1990-06-15']);

        $response = $this->get($this->path(CardVerificationController::urlFor($person)));

        $response->assertOk()
            ->assertSee('Valid card')
            ->assertSee('Neema')
            ->assertDontSee($person->nin)          // NIN kamili haionyeshwi
            ->assertDontSee('15/06/1990')
            ->assertDontSee($person->phone)
            ->assertHeader('X-Robots-Tag', 'noindex, nofollow');
    }

    public function test_tampered_or_unsigned_links_are_rejected(): void
    {
        $a = Person::factory()->create();
        $b = Person::factory()->create();
        $url = $this->path(CardVerificationController::urlFor($a));

        $this->get(str_replace("/card/{$a->id}?", "/card/{$b->id}?", $url))->assertForbidden()->assertSee('could not be verified');
        $this->get("/verify/card/{$a->id}")->assertForbidden();
    }

    public function test_inactive_and_archived_cards(): void
    {
        $person = Person::factory()->create(['status' => 'Inactive']);
        $url = $this->path(CardVerificationController::urlFor($person));

        $this->get($url)->assertOk()->assertSee('Record inactive');

        $person->delete();
        $this->get($url)->assertForbidden()->assertSee('could not be verified');
    }

    public function test_card_print_page_for_one_or_many_people(): void
    {
        $people = Person::factory()->count(3)->create();
        $user = $this->user('Registrar');

        $this->actingAs($user)->get("/people/{$people[0]->id}/card")->assertOk()->assertSee($people[0]->full_name)->assertSee('<svg', false);

        $this->actingAs($user)->get('/cards/print?ids=' . $people->pluck('id')->implode(','))
            ->assertOk()
            ->assertSee($people[1]->full_name)
            ->assertSee($people[2]->full_name);

        $this->assertDatabaseHas('activity_logs', ['action' => 'card.printed']);
    }

    /** Njia (path + query) ya URL, ili ombi la majaribio litumie host ya majaribio. */
    private function path(string $url): string
    {
        return parse_url($url, PHP_URL_PATH) . '?' . parse_url($url, PHP_URL_QUERY);
    }
}
