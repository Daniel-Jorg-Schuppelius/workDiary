<?php
/*
 * Created on   : Mon Nov 09 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : InvestmentApprovalInboxTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Feature\Investments;

use App\Enums\Investments\{InvestmentBudgetRequestStatus, InvestmentCaseStatus};
use App\Enums\Notification\NotificationEvent;
use App\Models\Approval\Approval;
use App\Models\Investments\{InvestmentBudgetRequest, InvestmentCase};
use App\Models\Platform\{Organization, User};
use App\Services\Approval\WorkQueue\ApprovalWorkItemSource;
use App\Services\Investments\InvestmentService;
use App\Services\WorkQueue\Dto\WorkItem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\PermissionRegistrar;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/**
 * MVP-1522: Freigabestufen eines Budgetantrags erscheinen im Genehmigungs-
 * Eingang und im Arbeitsvorrat bei der Rolle ihrer Stufenart (kaufmännisch →
 * Buchhaltung, Geschäftsleitung → Geschäftsführung), nie beim Antragsteller;
 * die Zuständigen der Stufe werden benachrichtigt.
 */
final class InvestmentApprovalInboxTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private User $requester;

    private User $accounting;

    private User $management;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
        app(PermissionRegistrar::class)->setPermissionsTeamId($this->organization->id);
        // Auch der Antragsteller trägt die Rolle der kaufmännischen Stufe.
        $this->requester = User::factory()->buchhaltung()->create(['organization_id' => $this->organization->id, 'name' => 'Rita Antrag']);
        $this->accounting = User::factory()->buchhaltung()->create(['organization_id' => $this->organization->id, 'name' => 'Bernd Buchhaltung']);
        $this->management = User::factory()->geschaeftsfuehrung()->create(['organization_id' => $this->organization->id, 'name' => 'Gabi Leitung']);
    }

    protected function tearDown(): void {
        app(PermissionRegistrar::class)->setPermissionsTeamId(null);
        parent::tearDown();
    }

    /** Antrag über dem Schwellenwert: kaufmännische Stufe, danach Geschäftsleitung. */
    private function submit(string $title = 'Ersatz Servicefahrzeug', string $amount = '25000.00', ?User $requester = null): InvestmentBudgetRequest {
        $requester ??= $this->requester;
        $case = InvestmentCase::query()->create([
            'organization_id' => $requester->organization_id,
            'title' => $title,
            'category' => 'machine',
            'status' => 'comparison',
            'created_by' => $requester->id,
        ]);

        return app(InvestmentService::class)->submitBudget($case, ['amount' => $amount], $requester);
    }

    private function step(InvestmentBudgetRequest $request, int $step): Approval {
        return $request->approvals()->where('step', $step)->orderByDesc('id')->firstOrFail();
    }

    /** @return list<string> */
    private function events(User $user): array {
        return array_values($user->notifications()->get()->map(static fn ($n): string => (string) (((array) $n->data)['event'] ?? ''))->all());
    }

    public function test_the_open_step_appears_for_the_responsible_role_only(): void {
        $request = $this->submit();

        $this->actingAs($this->accounting)->get(route('approvals.index'))->assertOk()
            ->assertSee('Investitionsantrag: Ersatz Servicefahrzeug')
            ->assertSee(route('investments.show', $request->investmentCase), false)
            ->assertSee('Version 1 · 25.000,00')
            ->assertSee('Kaufmännisch · Rolle: Buchhaltung');

        // Eigener Antrag: trotz passender Rolle nicht im Eingang.
        $this->actingAs($this->requester)->get(route('approvals.index'))->assertOk()
            ->assertDontSee('Investitionsantrag:');
        // Die Geschäftsleitung wartet auf die kaufmännische Stufe.
        $this->actingAs($this->management)->get(route('approvals.index'))->assertOk()
            ->assertDontSee('Investitionsantrag:');

        $other = Organization::factory()->create();
        app()->instance('currentOrganization', $other);
        app(PermissionRegistrar::class)->setPermissionsTeamId($other->id);
        $foreign = User::factory()->buchhaltung()->create(['organization_id' => $other->id]);
        $this->actingAs($foreign)->get(route('approvals.index'))->assertOk()
            ->assertDontSee('Investitionsantrag:');
    }

    public function test_decisions_in_the_inbox_take_the_way_of_the_file(): void {
        $request = $this->submit();

        $this->actingAs($this->accounting)
            ->post(route('approvals.decide', $this->step($request, 1)), ['decision' => 'approved'])
            ->assertRedirect(route('approvals.index'))
            ->assertSessionHas('success', 'Schritt genehmigt.');
        $this->assertSame(InvestmentBudgetRequestStatus::InApproval, $request->refresh()->status);
        $this->assertSame(['version' => 1, 'step' => 1, 'round' => 1], $request->investmentCase()->firstOrFail()->auditLogs()->where('event', 'investment.budget_step_approved')->sole()->changes);

        $this->actingAs($this->accounting)->get(route('approvals.index'))->assertOk()
            ->assertDontSee('Investitionsantrag:');
        $this->actingAs($this->management)->get(route('approvals.index'))->assertOk()
            ->assertSee('Investitionsantrag: Ersatz Servicefahrzeug')
            ->assertSee('Geschäftsleitung · Rolle: Geschäftsführung');

        $this->actingAs($this->management)
            ->post(route('approvals.decide', $this->step($request, 2)), ['decision' => 'approved'])
            ->assertRedirect(route('approvals.index'));

        $request->refresh();
        $this->assertSame(InvestmentBudgetRequestStatus::Approved, $request->status);
        $this->assertSame('25000.00', (string) ($request->snapshot['amount'] ?? ''));
        $this->assertSame(InvestmentCaseStatus::Approved, $request->investmentCase()->firstOrFail()->status);
        $this->actingAs($this->management)->get(route('approvals.index'))->assertOk()
            ->assertDontSee('Investitionsantrag:');
    }

    public function test_a_rejection_in_the_inbox_ends_the_request(): void {
        $request = $this->submit();

        $this->actingAs($this->accounting)
            ->post(route('approvals.decide', $this->step($request, 1)), ['decision' => 'rejected', 'reason' => 'Budget erschöpft'])
            ->assertRedirect(route('approvals.index'));

        $this->assertSame(InvestmentBudgetRequestStatus::Rejected, $request->refresh()->status);
        $this->assertSame(InvestmentCaseStatus::Rejected, $request->investmentCase()->firstOrFail()->status);
        $this->assertContains(NotificationEvent::InvestmentDecided->value, $this->events($this->requester));
    }

    public function test_self_approval_stays_blocked_in_the_inbox(): void {
        $step = $this->step($this->submit(), 1);

        $this->actingAs($this->requester)
            ->post(route('approvals.decide', $step), ['decision' => 'approved'])
            ->assertSessionHas('error', 'Selbstfreigabe ist nicht zulässig.');
        $this->assertNull($step->refresh()->decision);
    }

    public function test_delegation_goes_only_to_people_who_may_approve_investments(): void {
        $request = $this->submit();
        $worker = User::factory()->user()->create(['organization_id' => $this->organization->id]);

        $this->actingAs($this->accounting)
            ->post(route('approvals.decide', $this->step($request, 1)), ['decision' => 'delegated', 'reason' => 'Urlaub', 'delegate' => $worker->sqid])
            ->assertSessionHas('error', 'Bitte an eine Person delegieren, die Investitionen freigeben darf.');

        $this->actingAs($this->accounting)
            ->post(route('approvals.decide', $this->step($request, 1)), ['decision' => 'delegated', 'reason' => 'Urlaub', 'delegate' => $this->management->sqid])
            ->assertSessionHas('success', 'Schritt delegiert.');
        $this->actingAs($this->management)->get(route('approvals.index'))->assertOk()
            ->assertSee('Investitionsantrag: Ersatz Servicefahrzeug')
            ->assertSee('Persönlich');
        $this->assertContains(NotificationEvent::InvestmentApprovalRequested->value, $this->events($this->management));
    }

    public function test_the_responsible_people_of_each_step_are_notified(): void {
        $request = $this->submit();

        $sent = $this->accounting->notifications()->get()
            ->filter(static fn ($n): bool => (((array) $n->data)['event'] ?? null) === NotificationEvent::InvestmentApprovalRequested->value);
        $this->assertCount(1, $sent);
        $this->assertSame('Freigabe erbeten: Investition „Ersatz Servicefahrzeug“', (string) (((array) $sent->firstOrFail()->data)['title'] ?? ''));
        $this->assertNotContains(NotificationEvent::InvestmentApprovalRequested->value, $this->events($this->requester));
        $this->assertNotContains(NotificationEvent::InvestmentApprovalRequested->value, $this->events($this->management));

        app(InvestmentService::class)->approveBudget($request, $this->accounting);

        $this->assertContains(NotificationEvent::InvestmentApprovalRequested->value, $this->events($this->management));
        $this->assertCount(1, array_keys($this->events($this->accounting), NotificationEvent::InvestmentApprovalRequested->value, true));
    }

    /** MVP-1541: An der Akte gilt dieselbe Stufenzuständigkeit wie im Eingang — auch für Admins. */
    public function test_accounting_cannot_approve_the_management_step_on_the_file(): void {
        $request = $this->submit();
        $case = $request->investmentCase()->firstOrFail();
        $approve = route('investments.budget.approve', [$case, $request]);
        $admin = User::factory()->admin()->create(['organization_id' => $this->organization->id]);

        $this->actingAs($admin)->get(route('investments.show', $case))->assertOk()
            ->assertDontSee('action="' . $approve . '"', false)
            ->assertSee('Wartet auf Stufe 1: Kaufmännisch (Rolle Buchhaltung).');
        $this->actingAs($admin)->post($approve)->assertForbidden();

        $this->actingAs($this->accounting)->get(route('investments.show', $case))->assertOk()
            ->assertSee('action="' . $approve . '"', false);
        $this->actingAs($this->accounting)->post($approve)
            ->assertSessionHas('success', 'Freigabestufe erteilt — weitere Stufe offen (Vier-Augen).');

        $this->actingAs($this->accounting)->get(route('investments.show', $case))->assertOk()
            ->assertDontSee('action="' . $approve . '"', false)
            ->assertSee('Wartet auf Stufe 2: Geschäftsleitung (Rolle Geschäftsführung).');
        $this->actingAs($this->accounting)->post($approve)->assertForbidden();
        $this->actingAs($this->accounting)->post(route('investments.budget.reject', [$case, $request]), ['reason' => 'Zu teuer'])->assertForbidden();
        $this->assertNull($this->step($request, 2)->decision);
        $this->assertSame(InvestmentBudgetRequestStatus::InApproval, $request->refresh()->status);

        $this->actingAs($this->management)->get(route('investments.show', $case))->assertOk()
            ->assertSee('action="' . $approve . '"', false);
        $this->actingAs($this->management)->post($approve)->assertSessionHas('success', 'Budget genehmigt und eingefroren.');
        $this->assertSame(InvestmentBudgetRequestStatus::Approved, $request->refresh()->status);
    }

    public function test_the_work_queue_lists_the_step_for_the_responsible_person(): void {
        $request = $this->submit();
        $source = app(ApprovalWorkItemSource::class);
        $titles = fn (User $viewer): array => array_map(
            static fn (WorkItem $item): string => $item->title,
            iterator_to_array($source->items($viewer, $this->organization), false),
        );

        $this->assertTrue($source->available($this->management));
        $this->assertSame([], $titles($this->management));
        $this->assertSame([], $titles($this->requester));
        $this->assertSame(['Investitionsantrag: Ersatz Servicefahrzeug'], $titles($this->accounting));

        app(InvestmentService::class)->approveBudget($request, $this->accounting);

        $this->assertSame(['Investitionsantrag: Ersatz Servicefahrzeug'], $titles($this->management));
        $this->assertSame([], $titles($this->accounting));
    }
}
