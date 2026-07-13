<?php
/*
 * Created on   : Sun Jul 13 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : DatevBookingBatchPolicyTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Feature\Policies\Finance;

use App\Enums\Finance\DatevBatchStatus;
use App\Enums\User\Permission as P;
use App\Models\Finance\DatevBookingBatch;
use App\Policies\Finance\DatevBookingBatchPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\{BuildsPolicyActors, WithOrganization};
use Tests\TestCase;

/**
 * DATEV-Buchungsstapel (Feature 045): Anlegen/Finalisieren/Zuschneiden/Download
 * über finance.booking.export; exportierte Stapel sind UNVERÄNDERLICH
 * (GoBD-Festschreibung: finalize/reshape nur auf Drafts); die
 * Buchhaltungs-Konfiguration ist separat über finance.config geschützt.
 */
final class DatevBookingBatchPolicyTest extends TestCase {
    use BuildsPolicyActors;
    use RefreshDatabase;
    use WithOrganization;

    private DatevBookingBatchPolicy $policy;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
        $this->actAsTeam($this->organization);
        $this->policy = new DatevBookingBatchPolicy;
    }

    private function batch(DatevBatchStatus $status): DatevBookingBatch {
        $batch = new DatevBookingBatch;
        $batch->status = $status;

        return $batch;
    }

    public function test_booking_exporter_may_manage_drafts(): void {
        $exporter = $this->actorIn($this->organization, [P::FinanceBookingExport]);
        $draft = $this->batch(DatevBatchStatus::Draft);

        $this->assertTrue($this->policy->viewAny($exporter));
        $this->assertTrue($this->policy->view($exporter, $draft));
        $this->assertTrue($this->policy->create($exporter));
        $this->assertTrue($this->policy->finalize($exporter, $draft));
        $this->assertTrue($this->policy->reshape($exporter, $draft));
        $this->assertTrue($this->policy->download($exporter, $draft));
    }

    public function test_exported_batches_are_immutable(): void {
        $exporter = $this->actorIn($this->organization, [P::FinanceBookingExport]);
        $final = $this->batch(DatevBatchStatus::Exported);

        $this->assertFalse($this->policy->finalize($exporter, $final), 'Exportierter Stapel darf nicht erneut finalisiert werden.');
        $this->assertFalse($this->policy->reshape($exporter, $final), 'Exportierter Stapel ist unveränderlich (GoBD).');
        $this->assertTrue($this->policy->download($exporter, $final), 'Download bleibt erlaubt.');
    }

    public function test_configure_requires_finance_config(): void {
        $exporter = $this->actorIn($this->organization, [P::FinanceBookingExport]);
        $configurer = $this->actorIn($this->organization, [P::FinanceConfig]);

        $this->assertFalse($this->policy->configure($exporter));
        $this->assertTrue($this->policy->configure($configurer));
    }

    public function test_read_only_finance_user_cannot_export(): void {
        $viewer = $this->actorIn($this->organization, [P::FinanceViewAny]);
        $draft = $this->batch(DatevBatchStatus::Draft);

        $this->assertTrue($this->policy->viewAny($viewer));
        $this->assertFalse($this->policy->create($viewer));
        $this->assertFalse($this->policy->finalize($viewer, $draft));
        $this->assertFalse($this->policy->download($viewer, $draft));
    }

    public function test_orgless_or_permissionless_user_is_denied(): void {
        $this->assertFalse($this->policy->viewAny($this->actorIn($this->organization)));
        $this->assertFalse($this->policy->viewAny($this->orglessActor()));
    }
}
