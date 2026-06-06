<?php
/*
 * Created on   : Sat Jun 06 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : PasswordResetTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature;

use App\Models\User;
use App\Notifications\PasswordResetLink;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\{DB, Hash, Notification};
use Tests\TestCase;

class PasswordResetTest extends TestCase {
    use RefreshDatabase;

    public function test_forgot_password_page_renders(): void {
        $this->get(route('password.request'))->assertOk()->assertSee('Passwort vergessen');
    }

    public function test_reset_link_is_sent_and_token_stored(): void {
        Notification::fake();
        $user = User::factory()->user()->create(['email' => 'jane@firma.de']);

        $this->post(route('password.email'), ['email' => 'jane@firma.de'])
            ->assertRedirect()->assertSessionHas('status');

        $this->assertDatabaseHas('password_reset_tokens', ['email' => 'jane@firma.de']);
        Notification::assertSentTo($user, PasswordResetLink::class);
    }

    public function test_unknown_email_does_not_leak_but_still_ok(): void {
        Notification::fake();
        $this->post(route('password.email'), ['email' => 'nobody@firma.de'])
            ->assertRedirect()->assertSessionHas('status');
        $this->assertDatabaseMissing('password_reset_tokens', ['email' => 'nobody@firma.de']);
        Notification::assertNothingSent();
    }

    public function test_reset_form_renders_and_password_can_be_reset(): void {
        $user = User::factory()->user()->create(['email' => 'max@firma.de']);
        $token = 'plain-token-123456';
        DB::table('password_reset_tokens')->insert([
            'email' => 'max@firma.de',
            'token' => Hash::make($token),
            'created_at' => now(),
        ]);

        $this->get(route('password.reset', ['token' => $token]) . '?email=max@firma.de')
            ->assertOk()->assertSee('Passwort zurücksetzen');

        $this->post(route('password.update'), [
            'token' => $token,
            'email' => 'max@firma.de',
            'password' => 'NeuesPasswort!2026',
            'password_confirmation' => 'NeuesPasswort!2026',
        ])->assertRedirect(route('login'))->assertSessionHas('status');

        $this->assertTrue(Hash::check('NeuesPasswort!2026', $user->fresh()->password));
        $this->assertDatabaseMissing('password_reset_tokens', ['email' => 'max@firma.de']);
    }

    public function test_expired_or_invalid_token_is_rejected(): void {
        $user = User::factory()->user()->create(['email' => 'old@firma.de']);
        DB::table('password_reset_tokens')->insert([
            'email' => 'old@firma.de',
            'token' => Hash::make('right-token'),
            'created_at' => now()->subHours(5), // abgelaufen (expire 60 min)
        ]);

        $this->post(route('password.update'), [
            'token' => 'right-token',
            'email' => 'old@firma.de',
            'password' => 'NeuesPasswort!2026',
            'password_confirmation' => 'NeuesPasswort!2026',
        ])->assertSessionHasErrors('email');
    }
}
