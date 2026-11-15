<?php

namespace Tests\Feature;

use App\Models\Person;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AccessControlTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_are_sent_to_login(): void
    {
        foreach (['/dashboard', '/people', '/people/create', '/users', '/backups', '/archive', '/people/import'] as $uri) {
            $this->get($uri)->assertRedirect('/login');
        }
    }

    public function test_administrator_can_open_every_main_page(): void
    {
        $admin = $this->user('Administrator');
        $person = Person::factory()->create();

        foreach ([
            '/dashboard', '/people', '/people/create', '/people/import', "/people/{$person->id}", "/people/{$person->id}/edit",
            '/search?q=a', '/verifications', '/data-quality', '/reports', '/activity', '/account', '/geo',
            '/users', '/archive', '/backups', '/settings/api', "/people/{$person->id}/card",
        ] as $uri) {
            $this->actingAs($admin)->get($uri)->assertOk();
        }
    }

    public function test_viewer_is_read_only(): void
    {
        $viewer = $this->user('Viewer');
        $person = Person::factory()->create();

        $this->actingAs($viewer)->get("/people/{$person->id}")->assertOk();

        foreach (['/people/create', "/people/{$person->id}/edit", '/people/import', "/people/{$person->id}/card", '/users', '/backups', '/archive'] as $uri) {
            $this->actingAs($viewer)->get($uri)->assertRedirect(route('dashboard'));
        }

        $this->actingAs($viewer)->post('/people', $this->personForm())->assertRedirect(route('dashboard'));
        $this->assertSame(1, Person::count());
    }

    public function test_registrar_can_register_but_not_administer(): void
    {
        $registrar = $this->user('Registrar');
        $person = Person::factory()->create();

        $this->actingAs($registrar)->get('/people/create')->assertOk();
        $this->actingAs($registrar)->get('/people/import')->assertOk();

        foreach (['/users', '/backups', '/archive', '/settings/api'] as $uri) {
            $this->actingAs($registrar)->get($uri)->assertRedirect(route('dashboard'));
        }

        $this->actingAs($registrar)->delete("/people/{$person->id}", ['archive_reason' => 'Testing'])->assertRedirect(route('dashboard'));
        $this->assertFalse($person->fresh()->trashed());
    }

    public function test_login_and_inactive_accounts(): void
    {
        $this->user('Registrar', ['email' => 'clerk@example.com', 'password' => 'secret-pass-1']);
        $this->user('Registrar', ['email' => 'off@example.com', 'password' => 'secret-pass-1', 'is_active' => false]);

        $this->post('/login', ['email' => 'clerk@example.com', 'password' => 'wrong'])->assertSessionHasErrors('email');
        $this->assertGuest();

        $this->post('/login', ['email' => 'off@example.com', 'password' => 'secret-pass-1'])->assertSessionHasErrors('email');
        $this->assertGuest();

        $this->post('/login', ['email' => 'clerk@example.com', 'password' => 'secret-pass-1'])->assertRedirect(route('dashboard'));
        $this->assertAuthenticated();
    }

    public function test_login_is_rate_limited(): void
    {
        $this->user('Registrar', ['email' => 'clerk@example.com']);

        for ($i = 0; $i < 5; $i++) {
            $this->post('/login', ['email' => 'clerk@example.com', 'password' => 'wrong']);
        }

        $this->post('/login', ['email' => 'clerk@example.com', 'password' => 'password'])
            ->assertSessionHasErrors('email');
        $this->assertStringContainsString('Too many login attempts', session('errors')->first('email'));
        $this->assertGuest();
    }
}
