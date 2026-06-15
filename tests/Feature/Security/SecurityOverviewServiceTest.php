<?php
/*
 * Created on   : Sun Jun 15 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : SecurityOverviewServiceTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Security;

use App\Models\{AuditLog, Organization, User};
use App\Services\Security\SecurityOverviewService;
use Database\Seeders\PermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Read-only Aggregation der Sicherheitsübersicht (Feature 016, MVP):
 * 2FA-Zählung, Mandantentrennung, Token-Geheimnisschutz und der ehrliche
 * Session-Treiber-Fallback.
 */
class SecurityOverviewServiceTest extends TestCase {
    use RefreshDatabase;

    protected function setUp(): void {
        parent::setUp();
        $this->seed(PermissionsSeeder::class);
    }

    private function bindOrganization(Organization $organization): void {
        app()->instance('currentOrganization', $organization);
    }

    public function test_two_factor_count_is_correct_per_organization(): void {
        $org = Organization::factory()->create();

        $withTotp = User::factory()->create(['organization_id' => $org->id]);
        $withTotp->twoFactorCredentials()->create(['type' => 'totp', 'label' => 'App', 'confirmed_at' => now()]);
        // Zweiter, bestätigter Faktor desselben Users zählt NICHT doppelt.
        $withTotp->twoFactorCredentials()->create(['type' => 'email', 'label' => $withTotp->email, 'confirmed_at' => now()]);

        $withEmail = User::factory()->create(['organization_id' => $org->id]);
        $withEmail->twoFactorCredentials()->create(['type' => 'email', 'label' => $withEmail->email, 'confirmed_at' => now()]);

        // Unbestätigter Faktor zählt NICHT.
        $without = User::factory()->create(['organization_id' => $org->id]);
        $without->twoFactorCredentials()->create(['type' => 'totp', 'label' => 'Pending', 'confirmed_at' => null]);

        $this->bindOrganization($org);
        $result = app(SecurityOverviewService::class)->collect();

        $this->assertSame(2, $result['two_factor']['users_with_2fa']);
        $this->assertSame(3, $result['two_factor']['credentials']);
    }

    public function test_two_factor_count_respects_organization_boundary(): void {
        $orgA = Organization::factory()->create();
        $orgB = Organization::factory()->create();

        $userA = User::factory()->create(['organization_id' => $orgA->id]);
        $userA->twoFactorCredentials()->create(['type' => 'totp', 'label' => 'A', 'confirmed_at' => now()]);

        $userB = User::factory()->create(['organization_id' => $orgB->id]);
        $userB->twoFactorCredentials()->create(['type' => 'totp', 'label' => 'B', 'confirmed_at' => now()]);

        $this->bindOrganization($orgA);
        $result = app(SecurityOverviewService::class)->collect();

        // Nur der eigene Org-User wird gezählt, nicht der der Fremd-Org.
        $this->assertSame(1, $result['two_factor']['users_with_2fa']);
    }

    public function test_token_aggregation_never_exposes_token_value_or_hash(): void {
        $org = Organization::factory()->create();
        $user = User::factory()->create(['organization_id' => $org->id]);
        $created = $user->createToken('Mein Token', ['diary:read']);
        $plain = $created->plainTextToken;
        $hash = $created->accessToken->getAttribute('token');

        $this->bindOrganization($org);
        $tokens = app(SecurityOverviewService::class)->collect()['tokens'];

        $this->assertSame(1, $tokens['count']);
        $entry = $tokens['recent'][0];
        $this->assertSame('Mein Token', $entry['name']);
        $this->assertContains('diary:read', $entry['abilities']);

        // Der serialisierte Aggregat-Block enthält weder Klartext noch Hash.
        $flat = json_encode($tokens);
        $this->assertStringNotContainsString($plain, (string) $flat);
        $this->assertStringNotContainsString($hash, (string) $flat);
        $this->assertArrayNotHasKey('token', $entry);
    }

    public function test_support_access_only_includes_support_prefixed_events(): void {
        $org = Organization::factory()->create();
        $admin = User::factory()->admin()->create(['organization_id' => $org->id]);

        AuditLog::query()->create([
            'organization_id' => $org->id,
            'user_id' => $admin->id,
            'event' => 'support.session.started',
            'auditable_type' => User::class,
            'auditable_id' => $admin->id,
            'changes' => ['ticket' => 'WD-1'],
        ]);
        // Nicht-support-Ereignis darf nicht in die Supportzugriffe gezählt werden.
        AuditLog::query()->create([
            'organization_id' => $org->id,
            'user_id' => $admin->id,
            'event' => 'auth.login',
            'auditable_type' => User::class,
            'auditable_id' => $admin->id,
            'changes' => [],
        ]);

        $this->bindOrganization($org);
        $support = app(SecurityOverviewService::class)->collect()['support_access'];

        $this->assertSame(1, $support['count']);
        $this->assertSame('support.session.started', $support['recent'][0]['event']);
    }

    public function test_sessions_section_reports_driver_when_not_database(): void {
        // Test-Suite läuft mit SESSION_DRIVER=array → keine DB-Übersicht.
        config(['session.driver' => 'array']);
        $org = Organization::factory()->create();
        $this->bindOrganization($org);

        $sessions = app(SecurityOverviewService::class)->collect()['sessions'];

        $this->assertFalse($sessions['available']);
        $this->assertSame('array', $sessions['driver']);
    }

    public function test_encryption_status_reports_app_key_and_fields(): void {
        $org = Organization::factory()->create();
        $this->bindOrganization($org);

        $encryption = app(SecurityOverviewService::class)->collect()['encryption'];

        $this->assertTrue($encryption['app_key_set']);
        $this->assertSame('security:encrypt-existing', $encryption['command']);
        $this->assertArrayHasKey('users', $encryption['fields']);
        $this->assertContains('iban', $encryption['fields']['contact_bank_accounts']);
    }

    public function test_sessions_overview_lists_db_sessions_without_payload(): void {
        config(['session.driver' => 'database']);
        $org = Organization::factory()->create();
        $user = User::factory()->create(['organization_id' => $org->id]);

        DB::table('sessions')->insert([
            'id' => 'sess-active',
            'user_id' => $user->id,
            'ip_address' => '203.0.113.7',
            'user_agent' => 'Mozilla/5.0 (Test-Agent)',
            'payload' => 'SUPER-SECRET-PAYLOAD-DO-NOT-LEAK',
            'last_activity' => now()->getTimestamp(),
        ]);

        $this->bindOrganization($org);
        $sessions = app(SecurityOverviewService::class)->collect()['sessions'];

        $this->assertTrue($sessions['available']);
        $this->assertSame(1, $sessions['total']);
        $this->assertSame(1, $sessions['active']);

        $flat = (string) json_encode($sessions);
        $this->assertStringContainsString('203.0.113.7', $flat);
        // Der Session-Payload (Cookie-Inhalt) darf niemals auftauchen.
        $this->assertStringNotContainsString('SUPER-SECRET-PAYLOAD-DO-NOT-LEAK', $flat);
    }
}
