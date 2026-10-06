<?php
/*
 * Created on   : Mon Oct 05 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ContractObligationStatusEnumCastTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Feature\Contract;

use App\Enums\Contract\{ContractKind, ContractObligationKind, ContractObligationStatus, ContractPartnerType, ContractStatus, ContractTermKind, IndexationMethod};
use App\Models\Contract\{Contract, ContractObligation};
use App\Models\Platform\User;
use App\Services\Contract\ContractService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\PermissionRegistrar;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/**
 * Konsolidierungs-Audit 2026-10, k3-10 (Welle 8): der Vertragstermin führt
 * seinen Stand als Enum. Gegen die frühere Zeichenkette verglichen, bliebe
 * jeder Termin „offen“ und ein erledigter ließe sich erneut erledigen.
 */
final class ContractObligationStatusEnumCastTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private User $admin;

    private Contract $contract;

    protected function setUp(): void {
        parent::setUp();
        $this->travelTo('2026-10-05 12:00:00');
        $this->setUpOrganization();
        app(PermissionRegistrar::class)->setPermissionsTeamId($this->organization->id);
        $this->admin = User::factory()->admin()->create(['organization_id' => $this->organization->id]);
        $this->contract = app(ContractService::class)->create($this->organization, $this->admin, [
            'title' => 'Wartung Aufzugsanlage',
            'kind' => ContractKind::Maintenance->value,
            'status' => ContractStatus::Draft->value,
            'partner_type' => ContractPartnerType::Other->value,
            'partner_name' => 'TechWart GmbH',
            'term_kind' => ContractTermKind::OpenEnded->value,
            'starts_on' => '2026-01-01',
            'indexation_method' => IndexationMethod::None->value,
            'currency' => 'EUR',
            'value_period' => 'yearly',
        ]);
    }

    /** @param array<string, mixed> $attributes */
    private function obligation(ContractObligationStatus $status, array $attributes = []): ContractObligation {
        return $this->contract->obligations()->create(array_replace([
            'organization_id' => $this->organization->id,
            'kind' => ContractObligationKind::Review,
            'title' => 'Termin ' . $status->value,
            'due_on' => '2026-12-01',
            'warn_days_before' => 14,
            'status' => $status,
        ], $attributes))->refresh();
    }

    public function test_contract_page_shows_each_status_and_offers_completion_until_done(): void {
        $open = $this->obligation(ContractObligationStatus::Open);
        $done = $this->obligation(ContractObligationStatus::Done);
        $missed = $this->obligation(ContractObligationStatus::Missed);

        $html = (string) $this->actingAs($this->admin)->get(route('contracts.show', $this->contract))->assertOk()->getContent();

        foreach ([['badge-info badge-outline', $open], ['badge-success', $done], ['badge-error', $missed]] as [$classes, $obligation]) {
            $this->assertSame(1, preg_match('/' . $classes . '"[^>]*>\s*' . preg_quote(e($obligation->status->label()), '/') . '\s*</u', $html), "Kein Abzeichen für {$obligation->status->value}.");
        }
        foreach ([[$open, true], [$missed, true], [$done, false]] as [$obligation, $offered]) {
            $this->assertSame($offered, str_contains($html, 'action="' . route('contracts.obligations.complete', $obligation) . '"'), "Erledigen bei {$obligation->status->value}.");
        }
        $this->assertSame(1, substr_count($html, '<tr class="opacity-60">'));
    }

    public function test_only_open_obligations_warn(): void {
        foreach (ContractObligationStatus::cases() as $status) {
            $obligation = $this->obligation($status, ['due_on' => '2026-10-10']);

            $this->assertSame($status === ContractObligationStatus::Open, $obligation->isDueForWarning(), $status->value);
        }
        $this->assertSame(1, ContractObligation::query()->open()->count());
    }

    public function test_completing_a_recurring_obligation_twice_creates_one_successor(): void {
        $service = app(ContractService::class);
        $obligation = $this->obligation(ContractObligationStatus::Missed, ['recurring' => true, 'recurrence_months' => 12]);

        $service->completeObligation($obligation, $this->admin);
        $doneAt = $obligation->fresh()->done_at;
        $this->travel(1)->days();
        $service->completeObligation($obligation->fresh(), $this->admin);

        $this->assertSame(ContractObligationStatus::Done, $obligation->fresh()->status);
        $this->assertEquals($doneAt, $obligation->fresh()->done_at);
        $this->assertSame(2, $this->contract->obligations()->count());
        $this->assertSame(1, $this->contract->obligations()->open()->whereDate('due_on', '2027-12-01')->count());
    }
}
