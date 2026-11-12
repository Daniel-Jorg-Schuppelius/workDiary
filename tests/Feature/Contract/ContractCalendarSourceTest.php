<?php
/*
 * Created on   : Wed Nov 11 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ContractCalendarSourceTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Feature\Contract;

use App\Enums\Contract\{ContractObligationKind, ContractObligationStatus, ContractStatus, ContractTermKind};
use App\Enums\User\UserRole;
use App\Models\Contract\{Contract, ContractObligation};
use App\Models\Platform\Organization;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/** Vertragsfristen im Kalender (MVP-1617, H2-03). */
class ContractCalendarSourceTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    protected function setUp(): void {
        parent::setUp();
        Carbon::setTestNow('2026-11-11 10:00:00');
        $this->setUpOrganization();
    }

    /** @param array<string, mixed> $attributes */
    private function contract(array $attributes): Contract {
        return Contract::factory()->create(array_merge(['organization_id' => $this->organization->id, 'status' => ContractStatus::Active], $attributes));
    }

    /** @return list<array<string, mixed>> */
    private function events(string $start, string $end, bool $team = false): array {
        return (array) $this->getJson(route('calendar.events', array_filter(['start' => $start, 'end' => $end, 'team' => $team ? 1 : null])))->assertOk()->json();
    }

    public function test_obligations_period_ends_and_index_review_appear_for_the_responsible_person(): void {
        $admin = $this->orgAdmin();
        $other = $this->orgAdmin();
        $wartung = $this->contract([
            'title' => 'Wartung Zephyr', 'responsible_user_id' => $admin->id, 'term_kind' => ContractTermKind::Fixed,
            'ends_on' => '2026-11-30', 'auto_renew' => false, 'indexation_review_on' => '2026-11-20',
        ]);
        ContractObligation::query()->create([
            'organization_id' => $this->organization->id, 'contract_id' => $wartung->id, 'kind' => ContractObligationKind::NoticeDeadline,
            'title' => 'Kündigung Zephyr', 'due_on' => '2026-11-15', 'warn_days_before' => 30, 'status' => ContractObligationStatus::Open,
        ]);
        ContractObligation::query()->create([
            'organization_id' => $this->organization->id, 'contract_id' => $wartung->id, 'kind' => ContractObligationKind::Payment,
            'title' => 'Zahlung erledigt', 'due_on' => '2026-11-10', 'warn_days_before' => 0, 'status' => ContractObligationStatus::Done,
        ]);
        $this->contract([
            'title' => 'Miete Zephyr', 'responsible_user_id' => $other->id, 'term_kind' => ContractTermKind::Fixed,
            'ends_on' => '2025-11-25', 'auto_renew' => true, 'renew_period_months' => 12,
        ]);

        $this->actingAs($admin);
        $mine = array_column($this->events('2026-11-01', '2026-11-30'), 'title');
        $team = array_column($this->events('2026-11-01', '2026-11-30', team: true), 'title');

        $this->assertContains('Kündigung Zephyr · Wartung Zephyr', $mine);
        $this->assertContains(__('contract.calendar.end') . ' · Wartung Zephyr', $mine);
        $this->assertContains(__('contract.calendar.indexation_review') . ' · Wartung Zephyr', $mine);
        $this->assertNotContains('Zahlung erledigt · Wartung Zephyr', $mine);
        $this->assertNotContains(__('contract.calendar.renewal') . ' · Miete Zephyr', $mine);
        // Verlängerung jährlich ab 25.11.2025 — im Teamkalender am 25.11.2026.
        $this->assertContains(__('contract.calendar.renewal') . ' · Miete Zephyr', $team);
    }

    public function test_calendar_follows_contract_rights_module_and_organization(): void {
        $admin = $this->orgAdmin();
        $foreign = Organization::factory()->create();
        Contract::factory()->create([
            'organization_id' => $foreign->id, 'status' => ContractStatus::Active, 'title' => 'Fremdvertrag', 'term_kind' => ContractTermKind::Fixed, 'ends_on' => '2026-11-30',
        ]);
        $this->contract(['title' => 'Wartung Zephyr', 'term_kind' => ContractTermKind::Fixed, 'ends_on' => '2026-11-30']);
        $worker = $this->userWithRole(UserRole::User->value);

        $this->actingAs($admin);
        $team = array_column($this->events('2026-11-01', '2026-11-30', team: true), 'title');
        $this->assertContains(__('contract.calendar.end') . ' · Wartung Zephyr', $team);
        $this->assertNotContains(__('contract.calendar.end') . ' · Fremdvertrag', $team);
        $this->actingAs($worker);
        $this->assertSame([], array_values(array_filter(array_column($this->events('2026-11-01', '2026-11-30'), 'id'), static fn (string $id): bool => str_starts_with($id, 'contract-'))));
    }
}
