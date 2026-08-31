<?php
/*
 * Created on   : Sun Aug 31 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : AuthHardeningTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Feature\Security;

use App\Models\{Customer, User};
use App\Notifications\PasswordResetLink;
use App\Services\Security\SessionManagementService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\{DB, Notification};
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/**
 * Sicherheitsscan 2026-08-23: S-45 (Mail-Bombing über Passwort-vergessen)
 * und S-58 (Sanctum-API ohne Drosselung, unbegrenztes per_page).
 */
class AuthHardeningTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
    }

    /**
     * Der Limiter deckelt die Anfragen, die Sperrfrist die **Mails** — nötig
     * ist beides, weil verteilte Anfragen das IP-Limit umgehen.
     */
    public function test_zweite_reset_mail_binnen_der_sperrfrist_geht_nicht_raus(): void {
        Notification::fake();

        $user = User::factory()->create([
            'organization_id' => $this->organization->id,
            'email' => 'opfer@example.test',
        ]);

        $this->post(route('password.email'), ['email' => 'opfer@example.test'])->assertRedirect();
        Notification::assertSentToTimes($user, PasswordResetLink::class, 1);

        $this->post(route('password.email'), ['email' => 'opfer@example.test'])->assertRedirect();
        Notification::assertSentToTimes($user, PasswordResetLink::class, 1);
    }

    /** Die Antwort darf nicht verraten, ob wegen der Sperrfrist nichts kam. */
    public function test_antwort_bleibt_generisch(): void {
        Notification::fake();

        User::factory()->create([
            'organization_id' => $this->organization->id,
            'email' => 'bekannt@example.test',
        ]);

        $known = $this->post(route('password.email'), ['email' => 'bekannt@example.test']);
        $unknown = $this->post(route('password.email'), ['email' => 'unbekannt@example.test']);

        $this->assertSame($known->getSession()->get('status'), $unknown->getSession()->get('status'));
    }

    /** Nach Ablauf der Sperrfrist ist der Weg wieder frei. */
    public function test_nach_der_sperrfrist_kommt_wieder_eine_mail(): void {
        Notification::fake();

        $user = User::factory()->create([
            'organization_id' => $this->organization->id,
            'email' => 'spaeter@example.test',
        ]);

        $this->post(route('password.email'), ['email' => 'spaeter@example.test'])->assertRedirect();

        DB::table('password_reset_tokens')
            ->where('email', $user->email)
            ->update(['created_at' => now()->subMinutes(10)]);

        $this->post(route('password.email'), ['email' => 'spaeter@example.test'])->assertRedirect();

        Notification::assertSentToTimes($user, PasswordResetLink::class, 2);
    }

    /**
     * `per_page` ging ungeprüft an paginate() — ein gültiger Token konnte
     * damit den gesamten Bestand in einer Antwort anfordern.
     */
    public function test_api_klemmt_die_seitengroesse(): void {
        $user = User::factory()->admin()->create(['organization_id' => $this->organization->id]);
        Customer::factory()->count(3)->create(['organization_id' => $this->organization->id]);

        Sanctum::actingAs($user, ['*']);

        $response = $this->getJson('/api/customers?per_page=100000')->assertOk();

        $this->assertLessThanOrEqual(100, (int) $response->json('meta.per_page'));
    }

    /**
     * S-54: Die Sitzungsübersicht rendert für jede fremde Sitzung ein
     * Widerrufs-Formular. Stand dort die rohe `sessions.id`, landete der
     * Session-Identifier im HTML, in der Browser-Historie und in Proxy-Logs —
     * zusammen mit einem APP_KEY aus einem Backup ein Übernahmevektor.
     */
    public function test_sitzungsuebersicht_zeigt_keine_rohe_session_id(): void {
        // Die Übersicht listet Sitzungen nur beim Datenbank-Treiber.
        config(['session.driver' => 'database']);

        $admin = User::factory()->admin()->create(['organization_id' => $this->organization->id]);
        $other = User::factory()->create(['organization_id' => $this->organization->id]);

        $sessionId = 'aBcDeFgHiJkLmNoPqRsTuVwXyZ012345';
        DB::table('sessions')->insert([
            'id' => $sessionId,
            'user_id' => $other->id,
            'ip_address' => '203.0.113.7',
            'user_agent' => 'phpunit',
            'payload' => base64_encode('x'),
            'last_activity' => time(),
        ]);

        $response = $this->actingAs($admin)->get(route('admin.sessions.index'))->assertOk();

        $response->assertDontSee($sessionId, false);
        $response->assertSee(SessionManagementService::handleFor($sessionId), false);
    }

    /** Das Handle muss den Widerruf trotzdem tragen. */
    public function test_widerruf_ueber_das_handle_beendet_die_sitzung(): void {
        $admin = User::factory()->admin()->create(['organization_id' => $this->organization->id]);
        $other = User::factory()->create(['organization_id' => $this->organization->id]);

        $sessionId = 'zZyYxXwWvVuUtTsSrRqQpPoOnNmMlLkK';
        DB::table('sessions')->insert([
            'id' => $sessionId,
            'user_id' => $other->id,
            'ip_address' => '203.0.113.8',
            'user_agent' => 'phpunit',
            'payload' => base64_encode('x'),
            'last_activity' => time(),
        ]);

        $this->actingAs($admin)
            ->delete(route('admin.sessions.destroy', ['id' => SessionManagementService::handleFor($sessionId)]))
            ->assertRedirect();

        $this->assertDatabaseMissing('sessions', ['id' => $sessionId]);
    }

    public function test_api_akzeptiert_keine_negative_seitengroesse(): void {
        $user = User::factory()->admin()->create(['organization_id' => $this->organization->id]);
        Customer::factory()->create(['organization_id' => $this->organization->id]);

        Sanctum::actingAs($user, ['*']);

        $response = $this->getJson('/api/customers?per_page=-5')->assertOk();

        $this->assertGreaterThanOrEqual(1, (int) $response->json('meta.per_page'));
    }
}
