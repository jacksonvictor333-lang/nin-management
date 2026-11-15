<?php

namespace Tests\Feature;

use App\Support\Totp;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class AccountSecurityTest extends TestCase
{
    use RefreshDatabase;

    public function test_password_can_be_reset_with_the_emailed_link(): void
    {
        Notification::fake();
        $user = $this->user('Registrar', ['email' => 'clerk@example.com']);

        $this->post('/forgot-password', ['email' => 'clerk@example.com'])->assertSessionHas('status');
        // Email isiyokuwepo inapata ujumbe uleule.
        $this->post('/forgot-password', ['email' => 'nobody@example.com'])->assertSessionHas('status')->assertSessionHasNoErrors();

        $token = null;
        Notification::assertSentTo($user, ResetPassword::class, function ($n) use (&$token) {
            $token = $n->token;

            return true;
        });

        $this->get("/reset-password/{$token}?email=clerk@example.com")->assertOk();

        $this->post('/reset-password', ['token' => 'wrong', 'email' => 'clerk@example.com', 'password' => 'New-password-1', 'password_confirmation' => 'New-password-1'])
            ->assertSessionHasErrors('email');

        $this->post('/reset-password', ['token' => $token, 'email' => 'clerk@example.com', 'password' => 'New-password-1', 'password_confirmation' => 'New-password-1'])
            ->assertRedirect('/login');

        $this->assertTrue(Hash::check('New-password-1', $user->fresh()->password));
    }

    public function test_two_factor_setup_from_my_account(): void
    {
        $user = $this->user();

        $this->actingAs($user)->post('/account/two-factor')->assertRedirect();
        $secret = $user->fresh()->two_factor_secret;
        $this->assertNotEmpty($secret);
        $this->assertFalse($user->fresh()->hasTwoFactor(), 'Not on until a code is confirmed');

        $this->actingAs($user)->get('/account')->assertOk()->assertSee('Type the 6-digit code');

        $this->actingAs($user)->post('/account/two-factor/confirm', ['code' => '000000'])->assertSessionHasErrors('code', null, 'twoFactor');
        $this->actingAs($user)->post('/account/two-factor/confirm', ['code' => Totp::code($secret)])->assertSessionHas('recovery_codes');

        $this->assertTrue($user->fresh()->hasTwoFactor());
        $this->assertCount(8, $user->fresh()->two_factor_recovery_codes);

        // Kuzima kunahitaji nywila sahihi.
        $this->actingAs($user)->delete('/account/two-factor', ['current_password' => 'wrong'])->assertSessionHasErrors('current_password', null, 'twoFactor');
        $this->assertTrue($user->fresh()->hasTwoFactor());
        $this->actingAs($user)->delete('/account/two-factor', ['current_password' => 'password']);
        $this->assertFalse($user->fresh()->hasTwoFactor());
    }

    public function test_sign_in_requires_the_code_when_two_factor_is_on(): void
    {
        $secret = Totp::generateSecret();
        $user = $this->user('Administrator', [
            'email' => 'boss@example.com',
            'two_factor_secret' => $secret,
            'two_factor_confirmed_at' => now(),
            'two_factor_recovery_codes' => ['aaaaa-11111', 'bbbbb-22222'],
        ]);

        $this->post('/login', ['email' => 'boss@example.com', 'password' => 'password'])->assertRedirect('/two-factor-challenge');
        $this->assertGuest();

        $this->post('/two-factor-challenge', ['code' => '000000'])->assertSessionHasErrors('code');
        $this->assertGuest();

        $code = Totp::code($secret);
        $this->post('/two-factor-challenge', ['code' => $code])->assertRedirect(route('dashboard'));
        $this->assertAuthenticatedAs($user);

        // Nambari ileile haikubaliwi mara ya pili.
        $this->post('/logout');
        $this->post('/login', ['email' => 'boss@example.com', 'password' => 'password']);
        $this->post('/two-factor-challenge', ['code' => $code])->assertSessionHasErrors('code');
        $this->assertGuest();

        // Recovery code inafanya kazi mara moja tu.
        $this->post('/two-factor-challenge', ['recovery_code' => 'AAAAA-11111'])->assertRedirect(route('dashboard'));
        $this->assertSame(['bbbbb-22222'], $user->fresh()->two_factor_recovery_codes);
    }

    public function test_challenge_page_needs_a_password_first(): void
    {
        $this->get('/two-factor-challenge')->assertRedirect('/login');
        $this->post('/two-factor-challenge', ['code' => '123456'])->assertRedirect('/login');
    }
}
