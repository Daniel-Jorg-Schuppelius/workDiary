<?php
/*
 * Created on   : Thu Jun 11 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : IsmsConformityTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Isms;

use App\Enums\Isms\NormConformityStatus;
use App\Enums\Notification\{NotificationChannel, NotificationEvent};
use App\Models\{Document, Organization, User};
use App\Models\Isms\{IsmsCertificate, IsmsNormStatus, IsmsRequirement, IsmsScope};
use App\Models\Notification\NotificationRule;
use App\Services\Isms\ConformityService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/**
 * Zertifizierungen (Feature 046, Inkrement B): Statuskette (erlaubte und
 * verbotene Übergänge), STRIKTE 046-Regel (`certified` NUR mit hinterlegtem,
 * heute gültigem Zertifikat), Zertifikat-Pflichtfelder, org-sichere
 * Dokumentenreferenz, automatischer Verfall (expireOverdue), Scanner-Event
 * isms.certificateExpiring (Dedup), Permissions und Mandantengrenze.
 */
class IsmsConformityTest extends TestCase {
    use RefreshDatabase;

    public function test_index_shows_statuses_and_missing_pairs_for_scope(): void {
        $admin = User::factory()->admin()->create();
        $status = $this->makeStatus($admin, norm: 'ISO 9001', edition: '2015');

        // Anforderung mit weiterer Norm (ISO/IEC 27001:2022) ohne
        // Statuszeile ⇒ fehlendes norm/edition-Paar.
        IsmsRequirement::factory()->catalog()->create(['organization_id' => $admin->organization_id]);

        $response = $this->actingAs($admin)
            ->get(route('isms.conformity.index'))
            ->assertOk()
            ->assertSee($status->normLabel());

        $this->assertTrue($response->viewData('missingPairs')->contains('ISO/IEC 27001:2022'));
    }

    public function test_status_chain_allows_forward_transitions(): void {
        $admin = User::factory()->admin()->create();
        $status = $this->makeStatus($admin);

        $chain = [
            NormConformityStatus::GapAnalysisDone,
            NormConformityStatus::InProgress,
            NormConformityStatus::InternallyAuditReady,
            NormConformityStatus::ExternalAuditPlanned,
        ];

        foreach ($chain as $target) {
            $this->actingAs($admin)
                ->post(route('isms.conformity.transition', $status), ['status' => $target->value])
                ->assertRedirect()
                ->assertSessionHasNoErrors();

            $this->assertSame($target, $status->refresh()->status);
        }

        // Rücksprung: externalAuditPlanned → inProgress ist erlaubt.
        $this->actingAs($admin)
            ->post(route('isms.conformity.transition', $status), ['status' => NormConformityStatus::InProgress->value])
            ->assertRedirect()
            ->assertSessionHasNoErrors();
        $this->assertSame(NormConformityStatus::InProgress, $status->refresh()->status);
    }

    public function test_forbidden_transition_is_rejected(): void {
        $admin = User::factory()->admin()->create();
        $status = $this->makeStatus($admin);

        // notAssessed → internallyAuditReady überspringt die Kette.
        $this->actingAs($admin)
            ->from(route('isms.conformity.index'))
            ->post(route('isms.conformity.transition', $status), ['status' => NormConformityStatus::InternallyAuditReady->value])
            ->assertRedirect(route('isms.conformity.index'))
            ->assertSessionHasErrors('status');

        $this->assertSame(NormConformityStatus::NotAssessed, $status->refresh()->status);
    }

    public function test_certified_without_certificate_is_rejected(): void {
        $admin = User::factory()->admin()->create();
        $status = $this->makeStatus($admin, NormConformityStatus::ExternalAuditPlanned);

        $this->actingAs($admin)
            ->from(route('isms.conformity.index'))
            ->post(route('isms.conformity.transition', $status), ['status' => NormConformityStatus::Certified->value])
            ->assertRedirect(route('isms.conformity.index'))
            ->assertSessionHasErrors('status');

        $this->assertSame(NormConformityStatus::ExternalAuditPlanned, $status->refresh()->status);

        // Service direkt: ValidationException mit klarer Meldung.
        try {
            app(ConformityService::class)->transition($status, NormConformityStatus::Certified, $admin);
            $this->fail('Erwartete ValidationException blieb aus.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('status', $e->errors());
        }
    }

    public function test_certified_with_expired_certificate_is_rejected(): void {
        $admin = User::factory()->admin()->create();
        $status = $this->makeStatus($admin, NormConformityStatus::ExternalAuditPlanned);

        IsmsCertificate::factory()->expired()->create([
            'organization_id' => $admin->organization_id,
            'isms_norm_status_id' => $status->id,
        ]);

        $this->actingAs($admin)
            ->from(route('isms.conformity.index'))
            ->post(route('isms.conformity.transition', $status), ['status' => NormConformityStatus::Certified->value])
            ->assertRedirect(route('isms.conformity.index'))
            ->assertSessionHasErrors('status');

        $this->assertSame(NormConformityStatus::ExternalAuditPlanned, $status->refresh()->status);
    }

    public function test_certified_with_valid_certificate_succeeds(): void {
        $admin = User::factory()->admin()->create();
        $status = $this->makeStatus($admin, NormConformityStatus::ExternalAuditPlanned);

        $this->actingAs($admin)
            ->post(route('isms.conformity.certificates.store', $status), $this->certificatePayload())
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertSame(1, $status->certificates()->count());

        $this->actingAs($admin)
            ->post(route('isms.conformity.transition', $status), ['status' => NormConformityStatus::Certified->value])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertSame(NormConformityStatus::Certified, $status->refresh()->status);
    }

    public function test_expire_overdue_sets_certificate_expired(): void {
        $admin = User::factory()->admin()->create();

        $expired = $this->makeStatus($admin, NormConformityStatus::Certified);
        IsmsCertificate::factory()->expired()->create([
            'organization_id' => $admin->organization_id,
            'isms_norm_status_id' => $expired->id,
        ]);

        $valid = $this->makeStatus($admin, NormConformityStatus::Certified, norm: 'ISO 9001', edition: '2015');
        IsmsCertificate::factory()->create([
            'organization_id' => $admin->organization_id,
            'isms_norm_status_id' => $valid->id,
        ]);

        $count = app(ConformityService::class)->expireOverdue();

        $this->assertSame(1, $count);
        $this->assertSame(NormConformityStatus::CertificateExpired, $expired->refresh()->status);
        $this->assertSame(NormConformityStatus::Certified, $valid->refresh()->status, 'Heute gültiges Zertifikat bleibt zertifiziert');
    }

    public function test_certificate_requires_all_mandatory_fields(): void {
        $admin = User::factory()->admin()->create();
        $status = $this->makeStatus($admin);

        $this->actingAs($admin)
            ->from(route('isms.conformity.index'))
            ->post(route('isms.conformity.certificates.store', $status), [])
            ->assertRedirect(route('isms.conformity.index'))
            ->assertSessionHasErrors([
                'certified_organization',
                'scope_description',
                'certification_body',
                'certificate_no',
                'issued_on',
                'valid_from',
                'valid_until',
            ]);

        $this->assertSame(0, $status->certificates()->count());
    }

    public function test_certificate_validity_end_must_be_after_start(): void {
        $admin = User::factory()->admin()->create();
        $status = $this->makeStatus($admin);

        $payload = $this->certificatePayload([
            'valid_from' => now()->toDateString(),
            'valid_until' => now()->subDay()->toDateString(),
        ]);

        $this->actingAs($admin)
            ->from(route('isms.conformity.index'))
            ->post(route('isms.conformity.certificates.store', $status), $payload)
            ->assertRedirect(route('isms.conformity.index'))
            ->assertSessionHasErrors('valid_until');

        $this->assertSame(0, $status->certificates()->count());
    }

    public function test_certificate_document_must_belong_to_own_organization(): void {
        $admin = User::factory()->admin()->create();
        $status = $this->makeStatus($admin);

        $otherOrg = Organization::factory()->create(['slug' => 'isms-conf-doc-cross']);
        $otherAdmin = User::factory()->admin()->create(['organization_id' => $otherOrg->id]);
        app()->instance('currentOrganization', $otherOrg);
        $foreignDocument = Document::factory()->create([
            'organization_id' => $otherOrg->id,
            'created_by_user_id' => $otherAdmin->id,
        ]);

        app()->instance('currentOrganization', $admin->organization);
        $this->actingAs($admin)
            ->from(route('isms.conformity.index'))
            ->post(
                route('isms.conformity.certificates.store', $status),
                $this->certificatePayload(['document_id' => $foreignDocument->sqid]),
            )
            ->assertRedirect(route('isms.conformity.index'))
            ->assertSessionHasErrors('document_id');

        $this->assertSame(0, $status->certificates()->count());

        // Eigenes Dokument wird akzeptiert und org-sicher verknüpft.
        $ownDocument = Document::factory()->create([
            'organization_id' => $admin->organization_id,
            'created_by_user_id' => $admin->id,
        ]);

        $this->actingAs($admin)
            ->post(
                route('isms.conformity.certificates.store', $status),
                $this->certificatePayload(['document_id' => $ownDocument->sqid]),
            )
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        /** @var IsmsCertificate $certificate */
        $certificate = $status->certificates()->firstOrFail();
        $this->assertSame((int) $ownDocument->id, (int) $certificate->document_id);
    }

    public function test_store_creates_status_and_rejects_duplicate_norm(): void {
        $admin = User::factory()->admin()->create();
        app()->instance('currentOrganization', $admin->organization);
        $scope = IsmsScope::factory()->default()->create(['organization_id' => $admin->organization_id]);

        $payload = ['scope' => $scope->sqid, 'norm' => 'ISO/IEC 27001', 'edition' => '2022'];

        $this->actingAs($admin)
            ->post(route('isms.conformity.store'), $payload)
            ->assertRedirect(route('isms.conformity.index', ['scope' => $scope->sqid]))
            ->assertSessionHasNoErrors();

        /** @var IsmsNormStatus $status */
        $status = IsmsNormStatus::query()->firstOrFail();
        $this->assertSame(NormConformityStatus::NotAssessed, $status->status, 'Statuskette startet immer bei notAssessed');

        $this->actingAs($admin)
            ->from(route('isms.conformity.index'))
            ->post(route('isms.conformity.store'), $payload)
            ->assertRedirect(route('isms.conformity.index'))
            ->assertSessionHasErrors('norm');

        $this->assertSame(1, IsmsNormStatus::query()->count());
    }

    public function test_ensure_creates_status_rows_for_requirement_norms_idempotently(): void {
        $admin = User::factory()->admin()->create();
        app()->instance('currentOrganization', $admin->organization);
        $scope = IsmsScope::factory()->default()->create(['organization_id' => $admin->organization_id]);

        IsmsRequirement::factory()->catalog()->create(['organization_id' => $admin->organization_id]);
        IsmsRequirement::factory()->create(['organization_id' => $admin->organization_id]);

        $this->actingAs($admin)
            ->post(route('isms.conformity.ensure', $scope))
            ->assertRedirect(route('isms.conformity.index', ['scope' => $scope->sqid]));
        $this->assertSame(2, IsmsNormStatus::query()->where('isms_scope_id', $scope->id)->count());

        // Idempotent: zweiter Lauf legt nichts Neues an.
        $this->actingAs($admin)
            ->post(route('isms.conformity.ensure', $scope))
            ->assertRedirect();
        $this->assertSame(2, IsmsNormStatus::query()->where('isms_scope_id', $scope->id)->count());
    }

    public function test_scanner_fires_certificate_expiring_exactly_once_and_expires_overdue(): void {
        $admin = User::factory()->admin()->create();
        app()->instance('currentOrganization', $admin->organization);

        $teamlead = User::factory()->teamleitung()->create(['organization_id' => $admin->organization_id]);

        // Determinismus: nur In-App, damit Mail-Rendering den Test nicht
        // berührt; Empfänger-Rolle wie im Event-Default (teamleitung).
        NotificationRule::factory()->forEvent(NotificationEvent::IsmsCertificateExpiring)->create([
            'organization_id' => $admin->organization_id,
            'channels' => [NotificationChannel::InApp->value],
            'notify_affected' => false,
            'recipient_roles' => NotificationEvent::IsmsCertificateExpiring->defaultRecipientRoles(),
        ]);

        // Heute gültig, läuft in 10 Tagen ab ⇒ Event; certified bleibt.
        $expiring = $this->makeStatus($admin, NormConformityStatus::Certified);
        IsmsCertificate::factory()->expiringInDays(10)->create([
            'organization_id' => $admin->organization_id,
            'isms_norm_status_id' => $expiring->id,
        ]);

        // Bereits abgelaufen ⇒ automatischer Verfall im Scanner-Lauf.
        $overdue = $this->makeStatus($admin, NormConformityStatus::Certified, norm: 'ISO 9001', edition: '2015');
        IsmsCertificate::factory()->expired()->create([
            'organization_id' => $admin->organization_id,
            'isms_norm_status_id' => $overdue->id,
        ]);

        $this->artisan('notifications:scan-deadlines')->assertExitCode(0);
        $this->artisan('notifications:scan-deadlines')->assertExitCode(0);

        $this->assertSame(1, $teamlead->notifications()->count(), 'Dedup: genau eine Benachrichtigung');
        $data = (array) $teamlead->notifications()->first()?->data;
        $this->assertSame(NotificationEvent::IsmsCertificateExpiring->value, $data['event'] ?? null);

        $this->assertSame(NormConformityStatus::CertificateExpired, $overdue->refresh()->status);
        $this->assertSame(NormConformityStatus::Certified, $expiring->refresh()->status);
    }

    public function test_regular_user_cannot_access_conformity(): void {
        $user = User::factory()->user()->create();
        $admin = User::factory()->admin()->create(['organization_id' => $user->organization_id]);
        $status = $this->makeStatus($admin);

        $this->actingAs($user)->get(route('isms.conformity.index'))->assertForbidden();
        $this->actingAs($user)
            ->post(route('isms.conformity.transition', $status), ['status' => NormConformityStatus::GapAnalysisDone->value])
            ->assertForbidden();
    }

    public function test_geschaeftsfuehrung_can_view_but_not_manage(): void {
        $gf = User::factory()->geschaeftsfuehrung()->create();
        $admin = User::factory()->admin()->create(['organization_id' => $gf->organization_id]);
        $status = $this->makeStatus($admin);

        $this->actingAs($gf)->get(route('isms.conformity.index'))->assertOk();

        $this->actingAs($gf)
            ->post(route('isms.conformity.transition', $status), ['status' => NormConformityStatus::GapAnalysisDone->value])
            ->assertForbidden();

        $this->actingAs($gf)
            ->post(route('isms.conformity.certificates.store', $status), $this->certificatePayload())
            ->assertForbidden();

        $this->assertSame(NormConformityStatus::NotAssessed, $status->refresh()->status);
    }

    public function test_cross_organization_status_is_not_accessible(): void {
        $admin = User::factory()->admin()->create();
        $otherOrg = Organization::factory()->create(['slug' => 'isms-conf-cross']);
        $otherAdmin = User::factory()->admin()->create(['organization_id' => $otherOrg->id]);
        $foreignStatus = $this->makeStatus($otherAdmin);

        app()->instance('currentOrganization', $admin->organization);

        $this->actingAs($admin)
            ->post(route('isms.conformity.transition', $foreignStatus), ['status' => NormConformityStatus::GapAnalysisDone->value])
            ->assertNotFound();

        $this->actingAs($admin)
            ->post(route('isms.conformity.certificates.store', $foreignStatus), $this->certificatePayload())
            ->assertNotFound();

        $this->assertSame(NormConformityStatus::NotAssessed, $foreignStatus->refresh()->status);
        $this->assertSame(0, $foreignStatus->certificates()->count());
    }

    /**
     * Statuszeile im Default-Scope der Organisation des Users.
     */
    private function makeStatus(
        User $owner,
        NormConformityStatus $status = NormConformityStatus::NotAssessed,
        string $norm = 'ISO/IEC 27001',
        string $edition = '2022',
    ): IsmsNormStatus {
        app()->instance('currentOrganization', $owner->organization);

        $scope = IsmsScope::query()->firstOrCreate(
            ['organization_id' => $owner->organization_id, 'is_default' => true],
            ['name' => 'Gesamtorganisation'],
        );

        return IsmsNormStatus::factory()->status($status)->create([
            'organization_id' => $owner->organization_id,
            'isms_scope_id' => $scope->id,
            'norm' => $norm,
            'edition' => $edition,
        ]);
    }

    /**
     * Gültiges Zertifikats-Payload (046-Pflichtfelder) mit Overrides.
     *
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function certificatePayload(array $overrides = []): array {
        return $overrides + [
            'certified_organization' => 'Muster GmbH',
            'scope_description' => 'Informationssicherheits-Managementsystem der Gesamtorganisation',
            'certification_body' => 'Cert Authority AG',
            'certificate_no' => 'ISMS-2026-001',
            'issued_on' => now()->subDays(10)->toDateString(),
            'valid_from' => now()->subDays(5)->toDateString(),
            'valid_until' => now()->addYears(3)->toDateString(),
            'surveillance_audit_1_on' => now()->addYear()->toDateString(),
            'surveillance_audit_2_on' => now()->addYears(2)->toDateString(),
        ];
    }
}
