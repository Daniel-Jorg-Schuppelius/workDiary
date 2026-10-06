<?php
/*
 * Created on   : Mon Oct 05 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : JobPostingExpiryTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Feature\Applications;

use App\Enums\Applications\{JobPostingStatus, JobRequisitionStatus};
use App\Models\Applications\{JobPosting, JobRequisition};
use App\Models\Audit\AuditLog;
use App\Models\Platform\{Organization, User};
use App\Services\Licensing\FeatureFlagResolver;
use App\Settings\SettingScope;
use App\Support\{OrganizationContext, Setting};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\PermissionRegistrar;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/**
 * Entscheidung des Inhabers vom 2026-10-05: Eine veröffentlichte Anzeige mit
 * abgelaufenem Datum wird täglich auf „abgelaufen“ gesetzt
 * (`recruiting:expire-postings`). Vorher schrieb niemand den Stand — intern
 * stand die Anzeige weiter als „Veröffentlicht“.
 */
final class JobPostingExpiryTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private User $admin;

    protected function setUp(): void {
        parent::setUp();
        $this->travelTo('2026-10-05 12:00:00');
        $this->setUpOrganization();
        app(PermissionRegistrar::class)->setPermissionsTeamId($this->organization->id);
        $this->admin = User::factory()->admin()->create(['organization_id' => $this->organization->id]);
    }

    /** @param array<string, mixed> $attributes */
    private function posting(JobPostingStatus $status, array $attributes = [], ?Organization $organization = null, ?JobRequisition $requisition = null): JobPosting {
        $organization ??= $this->organization;
        $requisition ??= JobRequisition::query()->create(['organization_id' => $organization->id, 'title' => 'Servicetechniker:in', 'status' => JobRequisitionStatus::Open]);

        return JobPosting::query()->create(array_replace([
            'organization_id' => $organization->id,
            'job_requisition_id' => $requisition->id,
            'channel' => 'portal',
            'status' => $status,
            'published_at' => now()->subMonth(),
        ], $attributes));
    }

    private function statusOf(JobPosting $posting): JobPostingStatus {
        return JobPosting::query()->withoutGlobalScopes()->findOrFail($posting->id)->status;
    }

    /** Wie der Scheduler: ohne gebundene Organisation. */
    private function expire(array $parameters = []): \Illuminate\Testing\PendingCommand {
        app()->forgetInstance('currentOrganization');

        return $this->artisan('recruiting:expire-postings', $parameters);
    }

    // ── Der Lauf ─────────────────────────────────────────────────────────

    public function test_the_run_expires_exactly_the_published_postings_past_their_date(): void {
        $other = Organization::factory()->create();

        $pastExpiry = $this->posting(JobPostingStatus::Published, ['expires_at' => '2026-10-04']);
        $pastDeadline = $this->posting(JobPostingStatus::Published, ['channel' => 'website', 'public_slug' => 'technik', 'public_title' => 'Technik', 'application_deadline' => '2026-10-04']);
        $lastDay = $this->posting(JobPostingStatus::Published, ['expires_at' => '2026-10-05']);
        $open = $this->posting(JobPostingStatus::Published);
        $future = $this->posting(JobPostingStatus::Published, ['application_deadline' => '2026-10-06', 'expires_at' => '2026-12-31']);
        $paused = $this->posting(JobPostingStatus::Paused, ['expires_at' => '2026-10-01']);
        $closed = $this->posting(JobPostingStatus::Closed, ['expires_at' => '2026-10-01']);
        $draft = $this->posting(JobPostingStatus::Draft, ['expires_at' => '2026-10-01']);
        $foreignPast = $this->posting(JobPostingStatus::Published, ['expires_at' => '2026-10-04'], $other);
        $foreignOpen = $this->posting(JobPostingStatus::Published, [], $other);

        $this->expire()->expectsOutputToContain('3 Stellenanzeige(n) abgelaufen.')->assertExitCode(0);

        foreach ([$pastExpiry, $pastDeadline, $foreignPast] as $posting) {
            $this->assertSame(JobPostingStatus::Expired, $this->statusOf($posting));
        }
        foreach ([$lastDay, $open, $future, $foreignOpen] as $posting) {
            $this->assertSame(JobPostingStatus::Published, $this->statusOf($posting));
        }
        $this->assertSame(JobPostingStatus::Paused, $this->statusOf($paused));
        $this->assertSame(JobPostingStatus::Closed, $this->statusOf($closed));
        $this->assertSame(JobPostingStatus::Draft, $this->statusOf($draft));

        // Jede Zeile steht in der Kette ihrer eigenen Organisation.
        $audits = AuditLog::query()->withoutGlobalScopes()->where('event', 'recruiting.posting_expired')->get();
        $this->assertSame(
            [$pastExpiry->id => $this->organization->id, $pastDeadline->id => $this->organization->id, $foreignPast->id => $other->id],
            $audits->pluck('organization_id', 'auditable_id')->map(static fn ($id): int => (int) $id)->all(),
        );
        $this->assertEquals(['expires_at' => '2026-10-04', 'application_deadline' => null], $audits->firstWhere('auditable_id', $pastExpiry->id)->changes);
        $this->assertNull(OrganizationContext::current(), 'Der Lauf hat einen Organisations-Kontext hinterlassen.');

        // Zweiter Lauf: nichts mehr zu tun.
        $this->expire()->expectsOutputToContain('0 Stellenanzeige(n) abgelaufen.')->assertExitCode(0);
        $this->assertSame(3, AuditLog::query()->withoutGlobalScopes()->where('event', 'recruiting.posting_expired')->count());
        $this->assertSame(JobPostingStatus::Published, $this->statusOf($lastDay));

        // Der letzte gültige Tag ist vorbei.
        $this->travelTo('2026-10-06 12:00:00');
        $this->expire()->expectsOutputToContain('1 Stellenanzeige(n) abgelaufen.')->assertExitCode(0);
        $this->assertSame(JobPostingStatus::Expired, $this->statusOf($lastDay));
        $this->assertSame(JobPostingStatus::Published, $this->statusOf($future));
    }

    public function test_the_run_can_be_limited_to_one_organization(): void {
        $other = Organization::factory()->create();
        $own = $this->posting(JobPostingStatus::Published, ['expires_at' => '2026-10-04']);
        $foreign = $this->posting(JobPostingStatus::Published, ['expires_at' => '2026-10-04'], $other);

        $this->expire(['--organization' => (string) $other->id])->expectsOutputToContain('1 Stellenanzeige(n) abgelaufen.')->assertExitCode(0);

        $this->assertSame(JobPostingStatus::Published, $this->statusOf($own));
        $this->assertSame(JobPostingStatus::Expired, $this->statusOf($foreign));
    }

    /** Der Lauf und `isApplyable()` teilen eine Regel: was der Lauf ablaufen lässt, war schon nicht mehr bewerbbar. */
    public function test_the_run_follows_the_same_rule_as_the_apply_check(): void {
        $past = $this->posting(JobPostingStatus::Published, ['application_deadline' => '2026-10-04']);
        $today = $this->posting(JobPostingStatus::Published, ['application_deadline' => '2026-10-05']);

        $this->assertTrue($past->isPastDue());
        $this->assertFalse($past->isApplyable());
        $this->assertFalse($today->isPastDue());
        $this->assertTrue($today->isApplyable());
        $this->assertSame([$past->id], JobPosting::query()->pastDue()->pluck('id')->all());
    }

    public function test_an_expired_posting_leaves_the_public_career_area(): void {
        config(['license.feature_overrides' => ['module.applications' => true]]);
        app(FeatureFlagResolver::class)->flush();
        Setting::set('applications.portal.enabled', true, SettingScope::Organization, $this->organization);
        $this->posting(JobPostingStatus::Published, ['channel' => 'website', 'public_slug' => 'technik', 'public_title' => 'Technik gesucht', 'application_deadline' => '2026-10-04']);
        $base = '/karriere/' . $this->organization->slug;

        // Vor dem Lauf: gelistet und erreichbar, nur nicht mehr bewerbbar.
        $this->get($base)->assertOk()->assertSee('Technik gesucht');
        $this->get($base . '/stellen/technik')->assertOk()->assertSee(__('Diese Stelle nimmt derzeit keine Bewerbungen entgegen.'));

        $this->expire()->assertExitCode(0);

        // Nach dem Lauf: aus der Liste, über den Link weiter erreichbar — wie eine pausierte Anzeige.
        $this->get($base)->assertOk()->assertDontSee('Technik gesucht');
        $this->get($base . '/stellen/technik')->assertOk()->assertSee(__('Diese Stelle nimmt derzeit keine Bewerbungen entgegen.'));
    }

    // ── Oberfläche: keine Sackgasse ──────────────────────────────────────

    public function test_the_requisition_page_shows_expired_and_offers_close_and_republish(): void {
        $requisition = JobRequisition::query()->create(['organization_id' => $this->organization->id, 'title' => 'Servicetechniker:in', 'status' => JobRequisitionStatus::Open]);
        $career = $this->posting(JobPostingStatus::Expired, ['channel' => 'website', 'public_slug' => 'technik', 'public_title' => 'Technik', 'application_deadline' => '2026-10-04'], requisition: $requisition);
        $portal = $this->posting(JobPostingStatus::Expired, ['expires_at' => '2026-10-04'], requisition: $requisition);

        $html = (string) $this->actingAs($this->admin)->get(route('recruiting.requisitions.show', $requisition))->assertOk()->getContent();

        $this->assertSame(2, substr_count($html, e(JobPostingStatus::Expired->label())));
        $this->assertStringContainsString('action="' . route('recruiting.requisitions.postings.close', [$requisition, $career]) . '"', $html);
        $this->assertStringContainsString('action="' . route('recruiting.requisitions.postings.close', [$requisition, $portal]) . '"', $html);
        // „Veröffentlichen“ öffnet den Dialog, „Pausieren“ gibt es nur für freigegebene.
        $this->assertMatchesRegularExpression('/<a[^>]*href="' . preg_quote(route('recruiting.requisitions.career.edit', $requisition), '/') . '"[^>]*data-entry-modal-trigger|<a[^>]*data-entry-modal-trigger[^>]*href="' . preg_quote(route('recruiting.requisitions.career.edit', $requisition), '/') . '"/', $html);
        $this->assertStringNotContainsString('action="' . route('recruiting.requisitions.career.pause', $requisition) . '"', $html);
    }

    /**
     * Der Knopf „Veröffentlichen“ sandte bis 2026-10-05 ein leeres Formular
     * und scheiterte am Pflichtfeld — weder die erste noch eine erneute
     * Veröffentlichung war aus der Oberfläche möglich.
     */
    public function test_the_publish_dialog_republishes_an_expired_posting_with_a_new_date(): void {
        $requisition = JobRequisition::query()->create(['organization_id' => $this->organization->id, 'title' => 'Servicetechniker:in', 'status' => JobRequisitionStatus::Open]);
        $career = $this->posting(JobPostingStatus::Expired, [
            'channel' => 'website', 'public_slug' => 'technik', 'public_title' => 'Technik gesucht', 'public_summary' => 'Wartung beim Kunden.',
            'application_deadline' => '2026-10-04',
        ], requisition: $requisition);

        $this->actingAs($this->admin)->get(route('recruiting.requisitions.career.edit', $requisition))
            ->assertOk()
            ->assertSee('action="' . route('recruiting.requisitions.career.publish', $requisition) . '"', false)
            ->assertSee('value="Technik gesucht"', false)
            ->assertSee('value="Wartung beim Kunden."', false)
            ->assertSee('value="2026-10-04"', false);

        // Mit dem alten Datum liefe die Anzeige beim nächsten Lauf sofort wieder ab.
        $this->actingAs($this->admin)->post(route('recruiting.requisitions.career.publish', $requisition), ['public_title' => 'Technik gesucht', 'application_deadline' => '2026-10-04'])
            ->assertSessionHasErrors('application_deadline');
        $this->assertSame(JobPostingStatus::Expired, $this->statusOf($career));

        $this->actingAs($this->admin)->post(route('recruiting.requisitions.career.publish', $requisition), [
            'public_title' => 'Technik gesucht', 'public_summary' => 'Wartung beim Kunden.', 'application_deadline' => '2026-11-30', 'expires_at' => '2026-12-31',
        ])->assertSessionHas('success');

        $fresh = $career->fresh();
        $this->assertSame(JobPostingStatus::Published, $fresh->status);
        $this->assertSame('technik', $fresh->public_slug);
        $this->assertSame('2026-11-30', $fresh->application_deadline->toDateString());
        $this->assertSame('2026-12-31', $fresh->expires_at->toDateString());
        $this->assertTrue($fresh->isApplyable());
        $this->expire()->expectsOutputToContain('0 Stellenanzeige(n) abgelaufen.')->assertExitCode(0);
    }

    public function test_the_publish_dialog_suggests_the_requisition_title_for_a_first_publication(): void {
        $requisition = JobRequisition::query()->create(['organization_id' => $this->organization->id, 'title' => 'Monteur:in Außendienst', 'status' => JobRequisitionStatus::Open]);

        $this->actingAs($this->admin)->get(route('recruiting.requisitions.career.edit', $requisition))
            ->assertOk()
            ->assertSee('value="Monteur:in Außendienst"', false);
    }

    public function test_closing_follows_the_transition_table(): void {
        $requisition = JobRequisition::query()->create(['organization_id' => $this->organization->id, 'title' => 'Servicetechniker:in', 'status' => JobRequisitionStatus::Open]);

        foreach ([JobPostingStatus::Expired, JobPostingStatus::Paused, JobPostingStatus::Published] as $status) {
            $posting = $this->posting($status, requisition: $requisition);
            $this->actingAs($this->admin)->post(route('recruiting.requisitions.postings.close', [$requisition, $posting]))->assertSessionHas('success');
            $this->assertSame(JobPostingStatus::Closed, $this->statusOf($posting));
        }
    }
}
