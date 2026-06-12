<?php
/*
 * Created on   : Thu Jun 11 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : IsmsAuditPackageTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Isms;

use App\Enums\Isms\AuditPackageStatus;
use App\Models\Isms\{IsmsApplicabilityStatement, IsmsAuditPackage, IsmsAuditPackageToken, IsmsControl, IsmsRequirement, IsmsRisk, IsmsRiskAssessment, IsmsScope, IsmsSoftwareProduct};
use App\Models\{Organization, User};
use App\Services\Isms\AuditPackageService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\{Auth, Storage};
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/**
 * Auditpakete (Feature 046, Inkrement E / 044 „Auditbereitschaft"):
 * Entwurf-Anlage, Finalisierung (Datei + SHA-256 + Snapshot-Inhalt mit
 * ehrlicher Stichtags-Semantik: as_of_date vs. data_captured_at),
 * Unveränderlichkeit finalisierter Pakete, Integritätsprüfung
 * (isms:verify-packages erkennt Manipulation), zeitlich begrenzte
 * Prüfer-Tokens (nur für finalisierte Pakete, Klartext nur einmal,
 * öffentlicher Download ohne Login, abgelaufen/widerrufen ⇒ 404),
 * internes Download-Gate, Permissions und Mandantengrenze.
 */
class IsmsAuditPackageTest extends TestCase {
    use RefreshDatabase;

    public function test_store_creates_draft_package(): void {
        $admin = User::factory()->admin()->create();
        $scope = $this->makeScope($admin);

        $this->actingAs($admin)
            ->post(route('isms.packages.store'), [
                'scope' => $scope->sqid,
                'title' => 'Externes Audit 2026',
                'as_of_date' => '2026-06-30',
                'norm' => 'ISO/IEC 27001',
                'edition' => '2022',
            ])
            ->assertRedirect(route('isms.packages.index'))
            ->assertSessionHasNoErrors();

        /** @var IsmsAuditPackage $package */
        $package = IsmsAuditPackage::query()->firstOrFail();
        $this->assertSame(AuditPackageStatus::Draft, $package->status);
        $this->assertSame(1, $package->package_no);
        $this->assertSame('2026-06-30', $package->as_of_date->toDateString());
        $this->assertSame('ISO/IEC 27001', $package->norm);
        $this->assertNull($package->file_path);
        $this->assertNull($package->file_hash);
        $this->assertSame((int) $admin->id, (int) $package->created_by_user_id);
    }

    public function test_finalize_creates_file_hash_and_snapshot(): void {
        Storage::fake(AuditPackageService::DISK);

        $admin = User::factory()->admin()->create();
        $scope = $this->makeScope($admin);

        // Snapshot-Inhalt: 2 SoA-Aussagen, 1 Risiko mit freigegebener
        // Netto-Bewertung (+ Entwurf, der NICHT erscheinen darf),
        // 1 Maßnahme, 1 Softwareprodukt.
        foreach ([1, 2] as $i) {
            IsmsApplicabilityStatement::factory()->create([
                'organization_id' => $admin->organization_id,
                'isms_scope_id' => $scope->id,
                'isms_requirement_id' => IsmsRequirement::factory()->create([
                    'organization_id' => $admin->organization_id,
                    'ref_no' => 'M-0' . $i,
                ])->id,
            ]);
        }

        $risk = IsmsRisk::factory()->create([
            'organization_id' => $admin->organization_id,
            'isms_scope_id' => $scope->id,
            'title' => 'Serverraum-Brandrisiko',
        ]);
        IsmsRiskAssessment::factory()->net()->approved((int) $admin->id)->create([
            'organization_id' => $admin->organization_id,
            'isms_risk_id' => $risk->id,
            'assessment_no' => 1,
        ]);
        IsmsRiskAssessment::factory()->net()->create([
            'organization_id' => $admin->organization_id,
            'isms_risk_id' => $risk->id,
            'assessment_no' => 2,
        ]);

        IsmsControl::factory()->create([
            'organization_id' => $admin->organization_id,
            'title' => 'Brandfrüherkennung',
        ]);
        IsmsSoftwareProduct::factory()->create([
            'organization_id' => $admin->organization_id,
            'name' => 'Ubuntu Server',
        ]);

        $package = $this->makePackage($admin, $scope, asOfDate: '2026-06-30');

        $this->actingAs($admin)
            ->post(route('isms.packages.finalize', $package))
            ->assertRedirect(route('isms.packages.index'))
            ->assertSessionHasNoErrors();

        $package->refresh();
        $this->assertSame(AuditPackageStatus::Finalized, $package->status);
        $this->assertSame((int) $admin->id, (int) $package->finalized_by_user_id);
        $this->assertNotNull($package->finalized_at);
        $this->assertNotNull($package->file_path);
        $this->assertStringStartsWith(AuditPackageService::BASE_PATH . '/' . $admin->organization_id . '/', (string) $package->file_path);

        $disk = Storage::disk(AuditPackageService::DISK);
        $this->assertTrue($disk->exists((string) $package->file_path));

        $content = (string) $disk->get((string) $package->file_path);
        $this->assertSame(hash('sha256', $content), $package->file_hash, 'file_hash = SHA-256 der Datei');

        /** @var array<string, mixed> $json */
        $json = json_decode($content, true);

        // meta: ehrliche Stichtags-Semantik (Berichtsstichtag ≠ Datenstand).
        $this->assertSame('2026-06-30', $json['meta']['as_of_date']);
        $this->assertArrayHasKey('data_captured_at', $json['meta']);
        $this->assertArrayHasKey('app_version', $json['meta']);
        $this->assertSame($scope->name, $json['meta']['scope']);

        $this->assertCount(2, $json['soa']);
        $this->assertCount(1, $json['risks']);
        $this->assertSame('Serverraum-Brandrisiko', $json['risks'][0]['title']);
        // Nur die FREIGEGEBENE Netto-Bewertung erscheint (kein Entwurf).
        $this->assertCount(1, $json['risks'][0]['approved_assessments']);
        $this->assertSame('net', $json['risks'][0]['approved_assessments'][0]['kind']);
        $this->assertSame('B-1', $json['risks'][0]['approved_assessments'][0]['no']);

        $this->assertSame('Brandfrüherkennung', $json['controls'][0]['title']);
        $this->assertSame('Ubuntu Server', $json['software'][0]['name']);
    }

    public function test_finalized_package_is_immutable(): void {
        Storage::fake(AuditPackageService::DISK);

        $admin = User::factory()->admin()->create();
        $package = $this->makeFinalizedPackage($admin);

        try {
            $package->update(['title' => 'Nachträglich geändert']);
            $this->fail('Erwartete ValidationException (update) blieb aus.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('status', $e->errors());
        }

        try {
            $package->delete();
            $this->fail('Erwartete ValidationException (delete) blieb aus.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('status', $e->errors());
        }

        $this->assertSame('Auditpaket 2026', $package->refresh()->title);
        $this->assertNull($package->deleted_at);

        // Erneutes Finalisieren ist ebenfalls abgewiesen.
        $this->actingAs($admin)
            ->from(route('isms.packages.index'))
            ->post(route('isms.packages.finalize', $package))
            ->assertRedirect(route('isms.packages.index'))
            ->assertSessionHasErrors('status');
    }

    public function test_verify_command_ok_and_detects_manipulation(): void {
        Storage::fake(AuditPackageService::DISK);

        $admin = User::factory()->admin()->create();
        $package = $this->makeFinalizedPackage($admin);

        $this->artisan('isms:verify-packages')->assertExitCode(0);

        // UI-Button: Erfolgsfall als Flash.
        $this->actingAs($admin)
            ->post(route('isms.packages.verify', $package))
            ->assertRedirect(route('isms.packages.index'))
            ->assertSessionHas('success');

        // Manipulation: Dateiinhalt ändern ⇒ Hash-Mismatch.
        Storage::disk(AuditPackageService::DISK)->put((string) $package->file_path, '{"tampered":true}');

        $this->artisan('isms:verify-packages')->assertExitCode(1);
        $this->assertFalse(app(AuditPackageService::class)->verify($package->refresh()));

        $this->actingAs($admin)
            ->post(route('isms.packages.verify', $package))
            ->assertRedirect(route('isms.packages.index'))
            ->assertSessionHasErrors('file_hash');
    }

    public function test_token_requires_finalized_package(): void {
        $admin = User::factory()->admin()->create();
        $scope = $this->makeScope($admin);
        $draft = $this->makePackage($admin, $scope);

        $this->actingAs($admin)
            ->from(route('isms.packages.index'))
            ->post(route('isms.packages.tokens.store', $draft), ['label' => 'Auditor Müller', 'days' => 14])
            ->assertRedirect(route('isms.packages.index'))
            ->assertSessionHasErrors('status');

        $this->assertSame(0, IsmsAuditPackageToken::query()->count());
    }

    public function test_token_url_is_flashed_once_and_public_download_works_without_login(): void {
        Storage::fake(AuditPackageService::DISK);

        $admin = User::factory()->admin()->create();
        $package = $this->makeFinalizedPackage($admin);

        $this->actingAs($admin)
            ->post(route('isms.packages.tokens.store', $package), ['label' => 'Auditor Müller', 'days' => 14])
            ->assertRedirect(route('isms.packages.index'))
            ->assertSessionHasNoErrors()
            ->assertSessionHas('isms_package_token_url');

        $url = (string) session('isms_package_token_url');
        $plainToken = basename(parse_url($url, PHP_URL_PATH) ?: '');
        $this->assertSame(64, strlen($plainToken), 'Klartext-Token: 64 Hex-Zeichen');

        /** @var IsmsAuditPackageToken $token */
        $token = IsmsAuditPackageToken::query()->firstOrFail();
        $this->assertSame(hash('sha256', $plainToken), $token->token_hash, 'Nur der Hash wird gespeichert');
        $this->assertNotSame($plainToken, $token->token_hash);
        $this->assertSame('Auditor Müller', $token->label);
        $this->assertNull($token->last_accessed_at);

        // Folge-Request: der Klartext-Link ist NICHT erneut verfügbar (Flash).
        $this->actingAs($admin)
            ->get(route('isms.packages.index'))
            ->assertOk();
        $this->assertNull(session('isms_package_token_url'));

        // Öffentlicher Prüfer-Download OHNE Login und ohne Org-Session.
        Auth::logout();
        $this->app['auth']->forgetGuards();
        app()->forgetInstance('currentOrganization');

        $download = $this->get($url)->assertOk();
        $this->assertStringContainsString('attachment', (string) $download->headers->get('content-disposition'));

        $expected = (string) Storage::disk(AuditPackageService::DISK)->get((string) $package->file_path);
        $this->assertSame($expected, $download->streamedContent());

        $this->assertNotNull($token->refresh()->last_accessed_at, 'Abruf setzt last_accessed_at');
    }

    public function test_expired_or_revoked_token_returns_404(): void {
        Storage::fake(AuditPackageService::DISK);

        $admin = User::factory()->admin()->create();
        $package = $this->makeFinalizedPackage($admin);
        $service = app(AuditPackageService::class);

        // Abgelaufen ⇒ 404.
        $expired = $service->createToken($package, $admin, 'Abgelaufen', 1);
        $expired['model']->forceFill(['expires_at' => now()->subDay()])->save();

        // Widerrufen ⇒ 404 (vorher gültig).
        $revoked = $service->createToken($package, $admin, 'Widerrufen', 14);

        $this->actingAs($admin)
            ->post(route('isms.packages.tokens.revoke', $revoked['model']))
            ->assertRedirect(route('isms.packages.index'))
            ->assertSessionHasNoErrors();
        $this->assertNotNull($revoked['model']->refresh()->revoked_at);

        Auth::logout();
        app()->forgetInstance('currentOrganization');

        $this->get(route('audit-packages.public-download', ['token' => $expired['token']]))->assertNotFound();
        $this->get(route('audit-packages.public-download', ['token' => $revoked['token']]))->assertNotFound();
        $this->get(route('audit-packages.public-download', ['token' => str_repeat('0', 64)]))->assertNotFound();
    }

    public function test_internal_download_is_gate_checked(): void {
        Storage::fake(AuditPackageService::DISK);

        $admin = User::factory()->admin()->create();
        $package = $this->makeFinalizedPackage($admin);

        // geschaeftsfuehrung (isms.viewAny): interner Download erlaubt.
        $gf = User::factory()->geschaeftsfuehrung()->create(['organization_id' => $admin->organization_id]);
        $this->actingAs($gf)
            ->get(route('isms.packages.download', $package))
            ->assertOk();

        // Normaler User: kein isms.viewAny ⇒ verboten.
        $user = User::factory()->user()->create(['organization_id' => $admin->organization_id]);
        $this->actingAs($user)
            ->get(route('isms.packages.download', $package))
            ->assertForbidden();
    }

    public function test_regular_user_cannot_access_packages_and_gf_cannot_manage(): void {
        $admin = User::factory()->admin()->create();
        $scope = $this->makeScope($admin);
        $draft = $this->makePackage($admin, $scope);

        $user = User::factory()->user()->create(['organization_id' => $admin->organization_id]);
        $this->actingAs($user)->get(route('isms.packages.index'))->assertForbidden();

        $gf = User::factory()->geschaeftsfuehrung()->create(['organization_id' => $admin->organization_id]);
        $this->actingAs($gf)->get(route('isms.packages.index'))->assertOk();
        $this->actingAs($gf)
            ->post(route('isms.packages.store'), [
                'scope' => $scope->sqid,
                'title' => 'GF-Paket',
                'as_of_date' => now()->toDateString(),
            ])
            ->assertForbidden();
        $this->actingAs($gf)
            ->post(route('isms.packages.finalize', $draft))
            ->assertForbidden();
    }

    public function test_cross_organization_package_and_token_are_not_accessible(): void {
        Storage::fake(AuditPackageService::DISK);

        $admin = User::factory()->admin()->create();
        $otherOrg = Organization::factory()->create(['slug' => 'isms-pkg-cross']);
        $otherAdmin = User::factory()->admin()->create(['organization_id' => $otherOrg->id]);

        $foreignPackage = $this->makeFinalizedPackage($otherAdmin);
        $foreignToken = app(AuditPackageService::class)->createToken($foreignPackage, $otherAdmin, 'Fremd', 14);

        app()->instance('currentOrganization', $admin->organization);

        $this->actingAs($admin)
            ->post(route('isms.packages.finalize', $foreignPackage))
            ->assertNotFound();
        $this->actingAs($admin)
            ->post(route('isms.packages.verify', $foreignPackage))
            ->assertNotFound();
        $this->actingAs($admin)
            ->get(route('isms.packages.download', $foreignPackage))
            ->assertNotFound();
        $this->actingAs($admin)
            ->post(route('isms.packages.tokens.store', $foreignPackage), ['label' => 'X', 'days' => 14])
            ->assertNotFound();
        $this->actingAs($admin)
            ->post(route('isms.packages.tokens.revoke', $foreignToken['model']))
            ->assertNotFound();

        $this->assertNull($foreignToken['model']->refresh()->revoked_at);
    }

    // ── Helpers ─────────────────────────────────────────────────────────

    /** Default-Scope in der Organisation des Users (mit Org-Bindung). */
    private function makeScope(User $owner): IsmsScope {
        app()->instance('currentOrganization', $owner->organization);

        return IsmsScope::query()->firstOrCreate(
            ['organization_id' => $owner->organization_id, 'is_default' => true],
            ['name' => 'Gesamtorganisation'],
        );
    }

    private function makePackage(User $owner, IsmsScope $scope, string $asOfDate = '2026-06-30'): IsmsAuditPackage {
        return app(AuditPackageService::class)->create($owner, $scope, [
            'title' => 'Auditpaket 2026',
            'as_of_date' => $asOfDate,
        ]);
    }

    private function makeFinalizedPackage(User $owner): IsmsAuditPackage {
        $scope = $this->makeScope($owner);
        $package = $this->makePackage($owner, $scope);

        return app(AuditPackageService::class)->finalize($package, $owner);
    }
}
