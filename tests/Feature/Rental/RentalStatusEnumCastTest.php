<?php
/*
 * Created on   : Mon Oct 05 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : RentalStatusEnumCastTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Feature\Rental;

use App\Enums\Rental\{RentalCaseAssetStatus, RentalCaseStatus, RentalConditionItemState, RentalReservationKind, RentalReservationStatus};
use App\Models\Asset\Asset;
use App\Models\Customer\Customer;
use App\Models\Platform\User;
use App\Models\Rental\{RentalCase, RentalCaseAsset, RentalConditionItem, RentalProfile, RentalReservation};
use App\Services\Rental\{RentalAvailabilityService, RentalCaseService};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\PermissionRegistrar;
use Tests\Concerns\{WithOrganization, WithPortalVisibility};
use Tests\TestCase;

/**
 * Konsolidierungs-Audit 2026-10, k3-10 (Welle 7): Leihobjekt, Belegungsfenster
 * und Checklistenposition führen Status bzw. Befund als Enum. Ein Vergleich
 * gegen die frühere Zeichenkette ist danach still falsch — Übergabe, Rücknahme,
 * Tausch und Storno wären in der Akte nicht mehr erreichbar.
 */
final class RentalStatusEnumCastTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;
    use WithPortalVisibility;

    private User $admin;

    private Customer $customer;

    private Asset $excavator;

    private Asset $roller;

    protected function setUp(): void {
        parent::setUp();
        $this->travelTo('2026-10-05 12:00:00');
        $this->setUpOrganization();
        app(PermissionRegistrar::class)->setPermissionsTeamId($this->organization->id);
        $this->admin = User::factory()->admin()->create(['organization_id' => $this->organization->id]);
        $this->customer = Customer::factory()->create(['organization_id' => $this->organization->id]);
        $this->allowPortal($this->customer);
        $this->excavator = $this->rentable('Minibagger');
        $this->roller = $this->rentable('Walze');
    }

    private function rentable(string $name): Asset {
        $asset = Asset::factory()->create(['organization_id' => $this->organization->id, 'name' => $name]);
        RentalProfile::query()->create(['organization_id' => $this->organization->id, 'asset_id' => $asset->id, 'is_rentable' => true]);

        return $asset;
    }

    private function reservedCase(): RentalCase {
        $service = app(RentalCaseService::class);
        $case = $service->open($this->organization, $this->admin, [
            'customer_id' => $this->customer->id,
            'starts_at' => now()->addDay()->setTime(8, 0),
            'ends_at' => now()->addDays(3)->setTime(17, 0),
        ], [$this->excavator->id, $this->roller->id]);

        return $service->reserve($case, $this->admin);
    }

    private function position(RentalCase $case, Asset $asset): RentalCaseAsset {
        return $case->caseAssets()->where('asset_id', $asset->id)->sole();
    }

    private function page(RentalCase $case): string {
        return (string) $this->actingAs($this->admin)->get(route('rental.show', $case))->assertOk()->getContent();
    }

    /** @return list<string> Asset-Sqids der Protokollformulare mit diesem Ziel */
    private function reportForms(string $html, string $action): array {
        preg_match_all('/<form[^>]*action="' . preg_quote($action, '/') . '"(?:(?!<\/form>).)*?name="asset_id" value="([^"]+)"/s', $html, $matches);

        return $matches[1];
    }

    /** @param list<Asset> $assets */
    private function assertSwapOffered(array $assets, RentalCase $case, string $html): void {
        foreach ([$this->excavator, $this->roller, ...$assets] as $asset) {
            $position = $case->caseAssets()->where('asset_id', $asset->id)->first();
            if ($position === null) {
                continue;
            }
            $offered = str_contains($html, 'name="case_asset_id" value="' . $position->sqid . '"');
            $this->assertSame(in_array($asset, $assets, true), $offered, "Tausch für {$asset->name}");
        }
    }

    /** Tabelle der Karte mit diesem Titel — die Kopfzeile der Akte trägt eigene Abzeichen. */
    private function cardTable(string $html, string $title): string {
        $start = strpos($html, '>' . e($title) . '</span>');
        $this->assertNotFalse($start, "Karte „{$title}“ fehlt");

        return substr($html, $start, (int) strpos($html, '</table>', $start) - $start);
    }

    private function assertBadge(string $label, int $times, string $html): void {
        $this->assertSame($times, preg_match_all('/badge[^>]*>\s*' . preg_quote(e($label), '/') . '\s*</u', $html), "Abzeichen „{$label}“");
    }

    // ── Leihobjekte ──────────────────────────────────────────────────────

    public function test_case_file_offers_handover_return_and_swap_by_position_status(): void {
        $service = app(RentalCaseService::class);
        $case = $this->reservedCase();
        $handover = route('rental.handover', $case);
        $return = route('rental.return', $case);
        $both = [$this->excavator->sqid, $this->roller->sqid];

        $html = $this->page($case);
        $this->assertEqualsCanonicalizing($both, $this->reportForms($html, $handover));
        $this->assertSame([], $this->reportForms($html, $return));
        $this->assertSwapOffered([$this->excavator, $this->roller], $case, $html);
        $this->assertBadge(RentalCaseAssetStatus::Planned->label(), 2, $this->cardTable($html, (string) __('Leihobjekte')));

        // Eine Position übergeben: die Akte bleibt reserviert, die Rücknahme wartet auf die Akte.
        $service->handover($case->fresh(), $this->excavator, $this->admin, ['condition' => 'good']);
        $html = $this->page($case);
        $this->assertSame([$this->roller->sqid], $this->reportForms($html, $handover));
        $this->assertSame([], $this->reportForms($html, $return));
        $positions = $this->cardTable($html, (string) __('Leihobjekte'));
        $this->assertBadge(RentalCaseAssetStatus::Planned->label(), 1, $positions);
        $this->assertBadge(RentalCaseAssetStatus::HandedOver->label(), 1, $positions);

        $service->handover($case->fresh(), $this->roller, $this->admin, ['condition' => 'good']);
        $html = $this->page($case);
        $this->assertSame([], $this->reportForms($html, $handover));
        $this->assertEqualsCanonicalizing($both, $this->reportForms($html, $return));
        $this->assertSwapOffered([$this->excavator, $this->roller], $case, $html);

        // Zurückgenommene und getauschte Positionen bieten nichts mehr an.
        $service->returnAsset($case->fresh(), $this->excavator, $this->admin, ['condition' => 'good', 'follow_up' => 'none']);
        $loader = $this->rentable('Radlader');
        $service->swapAsset($case->fresh(), $this->position($case, $this->roller), $loader, $this->admin, 'Motorschaden');
        $html = $this->page($case);
        $this->assertSame([$loader->sqid], $this->reportForms($html, $return));
        $this->assertSwapOffered([$loader], $case, $html);
        $positions = $this->cardTable($html, (string) __('Leihobjekte'));
        $this->assertBadge(RentalCaseAssetStatus::Returned->label(), 1, $positions);
        $this->assertBadge(RentalCaseAssetStatus::Swapped->label(), 1, $positions);
        $this->assertBadge(RentalCaseAssetStatus::HandedOver->label(), 1, $positions);
    }

    public function test_case_follows_its_positions_through_handover_and_return(): void {
        $service = app(RentalCaseService::class);
        $case = $this->reservedCase();

        $service->handover($case->fresh(), $this->excavator, $this->admin, ['condition' => 'good']);
        $this->assertSame(RentalCaseAssetStatus::HandedOver, $this->position($case, $this->excavator)->status);
        $this->assertSame(RentalCaseAssetStatus::Planned, $this->position($case, $this->roller)->status);
        $this->assertSame(RentalCaseStatus::Reserved, $case->fresh()->status);

        $service->handover($case->fresh(), $this->roller, $this->admin, ['condition' => 'good']);
        $this->assertSame(RentalCaseStatus::HandedOver, $case->fresh()->status);

        $service->returnAsset($case->fresh(), $this->excavator, $this->admin, ['condition' => 'good', 'follow_up' => 'none']);
        $this->assertSame(RentalCaseAssetStatus::Returned, $this->position($case, $this->excavator)->status);
        $this->assertSame(RentalCaseStatus::HandedOver, $case->fresh()->status);
        $this->assertSame(RentalReservationStatus::Completed, $case->reservations()->where('asset_id', $this->excavator->id)->sole()->status);
        $this->assertSame(RentalReservationStatus::Active, $case->reservations()->where('asset_id', $this->roller->id)->sole()->status);

        $service->returnAsset($case->fresh(), $this->roller, $this->admin, ['condition' => 'good', 'follow_up' => 'none']);
        $this->assertSame(RentalCaseStatus::Returned, $case->fresh()->status);
    }

    public function test_swap_closes_the_old_position_and_the_replacement_takes_its_place(): void {
        $service = app(RentalCaseService::class);
        $case = $this->reservedCase();
        $loader = $this->rentable('Radlader');

        // Reservierte Akte: der Ersatz ist ebenfalls nur geplant und hart reserviert.
        $planned = $service->swapAsset($case->fresh(), $this->position($case, $this->excavator), $loader, $this->admin);
        $this->assertSame(RentalCaseAssetStatus::Planned, $planned->fresh()->status);
        $this->assertSame(RentalCaseAssetStatus::Swapped, $this->position($case, $this->excavator)->status);
        $this->assertSame($planned->id, $this->position($case, $this->excavator)->replaced_by_id);
        $this->assertSame(RentalReservationStatus::Completed, $case->reservations()->where('asset_id', $this->excavator->id)->sole()->status);
        $replacement = $case->reservations()->where('asset_id', $loader->id)->sole();
        $this->assertSame(RentalReservationStatus::Active, $replacement->status);
        $this->assertSame(RentalReservationKind::Hard, $replacement->kind);

        // Übergebene Akte: der Ersatz gilt als übergeben; die getauschte Position hält die Akte nicht offen.
        $service->handover($case->fresh(), $loader, $this->admin, ['condition' => 'good']);
        $service->handover($case->fresh(), $this->roller, $this->admin, ['condition' => 'good']);
        $this->assertSame(RentalCaseStatus::HandedOver, $case->fresh()->status);
        $crane = $this->rentable('Kran');
        $handedOver = $service->swapAsset($case->fresh(), $this->position($case, $this->roller), $crane, $this->admin);
        $this->assertSame(RentalCaseAssetStatus::HandedOver, $handedOver->fresh()->status);

        $service->returnAsset($case->fresh(), $loader, $this->admin, ['condition' => 'good', 'follow_up' => 'none']);
        $service->returnAsset($case->fresh(), $crane, $this->admin, ['condition' => 'good', 'follow_up' => 'none']);
        $this->assertSame(RentalCaseStatus::Returned, $case->fresh()->status);
    }

    public function test_customer_portal_shows_the_position_status_as_text(): void {
        $case = $this->reservedCase();
        app(RentalCaseService::class)->handover($case->fresh(), $this->excavator, $this->admin, ['condition' => 'good']);
        $portalUser = User::factory()->kunde((int) $this->customer->id, (int) $this->organization->id)->create(['organization_id' => $this->organization->id]);

        $html = (string) $this->actingAs($portalUser, 'customer')->get(route('customer.rentals.show', $case))->assertOk()->getContent();

        $this->assertBadge(RentalCaseAssetStatus::HandedOver->label(), 1, $html);
        $this->assertBadge(RentalCaseAssetStatus::Planned->label(), 1, $html);
    }

    // ── Belegungsfenster ─────────────────────────────────────────────────

    /** Entscheidung 2026-10-05: Fenster einer Akte enden über Rücknahme, Tausch oder Storno der Akte — nie einzeln. */
    public function test_case_windows_carry_no_cancellation_and_the_direct_call_stays_rejected(): void {
        $service = app(RentalCaseService::class);
        $case = $this->reservedCase();
        $cancelForms = fn (string $html): int => $case->reservations()->get()
            ->filter(fn (RentalReservation $reservation): bool => str_contains($html, 'action="' . route('rental.reservations.cancel', $reservation) . '"'))
            ->count();

        $html = $this->page($case);
        $this->assertSame(0, $cancelForms($html));
        $this->assertBadge(RentalReservationStatus::Active->label(), 2, $this->cardTable($html, (string) __('Belegungsfenster')));
        $this->assertFalse(app(RentalAvailabilityService::class)->isAvailable($this->excavator, $case->starts_at, $case->ends_at));

        // Der Kalender zeigt die Aktenfenster, bietet das Storno aber nur für Fenster ohne Akte an.
        $calendar = $this->actingAs($this->admin)->get(route('rental.calendar', ['month' => '2026-10']))->assertOk();
        $this->assertNotSame([], $calendar->viewData('itemsByDay'));
        $this->assertSame([], $calendar->viewData('freeWindows')->all());
        $this->assertSame(0, $cancelForms((string) $calendar->getContent()));

        $window = $case->reservations()->where('asset_id', $this->excavator->id)->sole();
        $this->actingAs($this->admin)->from(route('rental.show', $case))
            ->post(route('rental.reservations.cancel', $window))
            ->assertRedirect(route('rental.show', $case))
            ->assertSessionHasErrors('reservation');
        $this->assertSame(RentalReservationStatus::Active, $window->fresh()->status);
        $this->assertNull($window->fresh()->cancelled_at);

        $service->cancel($case->fresh(), $this->admin, 'Kunde sagt ab');
        $html = $this->page($case);
        $this->assertSame(0, $cancelForms($html));
        $this->assertBadge(RentalReservationStatus::Cancelled->label(), 2, $this->cardTable($html, (string) __('Belegungsfenster')));
        $this->assertSame([RentalReservationStatus::Cancelled], $case->reservations()->get()->pluck('status')->unique()->all());
        // Stornierte Fenster belegen den Kalender nicht mehr.
        $this->assertTrue(app(RentalAvailabilityService::class)->isAvailable($this->excavator, $case->starts_at, $case->ends_at));
    }

    private function maintenanceWindow(): RentalReservation {
        $this->actingAs($this->admin)->post(route('rental.reservations.store'), [
            'asset_id' => $this->excavator->sqid,
            'kind' => RentalReservationKind::Maintenance->value,
            'starts_at' => '2026-10-12 08:00',
            'ends_at' => '2026-10-12 16:00',
            'note' => 'Ölwechsel',
        ])->assertSessionHasNoErrors();

        return RentalReservation::query()->sole();
    }

    public function test_free_calendar_window_is_listed_and_leaves_the_calendar_when_cancelled(): void {
        $window = $this->maintenanceWindow();
        $this->assertSame(RentalReservationStatus::Active, $window->status);
        $form = 'action="' . route('rental.reservations.cancel', $window) . '"';
        $calendar = fn () => $this->actingAs($this->admin)->get(route('rental.calendar', ['month' => '2026-10']))->assertOk();

        $page = $calendar();
        $this->assertCount(1, $page->viewData('itemsByDay'));
        $this->assertSame([$window->id], $page->viewData('freeWindows')->modelKeys());
        $page->assertSee($form, false)->assertSee('Ölwechsel');
        // Ein anderer Monat listet das Fenster nicht.
        $this->actingAs($this->admin)->get(route('rental.calendar', ['month' => '2026-12']))->assertOk()->assertDontSee($form, false);

        $this->actingAs($this->admin)->post(route('rental.reservations.cancel', $window))->assertSessionHasNoErrors();

        $this->assertSame(RentalReservationStatus::Cancelled, $window->fresh()->status);
        $this->assertNotNull($window->fresh()->cancelled_at);
        $page = $calendar();
        $this->assertCount(0, $page->viewData('itemsByDay'));
        $page->assertDontSee($form, false);
        $this->assertTrue(app(RentalAvailabilityService::class)->isAvailable($this->excavator, $window->starts_at, $window->ends_at));
    }

    public function test_second_cancellation_of_a_window_changes_nothing(): void {
        $window = $this->maintenanceWindow();
        $this->actingAs($this->admin)->post(route('rental.reservations.cancel', $window))->assertSessionHasNoErrors();
        $cancelledAt = $window->fresh()->cancelled_at;
        $this->assertNotNull($cancelledAt);

        $this->travel(10)->minutes();
        $this->actingAs($this->admin)->post(route('rental.reservations.cancel', $window))->assertSessionHasErrors('reservation');

        $this->assertSame(RentalReservationStatus::Cancelled, $window->fresh()->status);
        $this->assertTrue($cancelledAt->equalTo($window->fresh()->cancelled_at));
    }

    public function test_cancelling_a_free_window_needs_the_manage_right(): void {
        $window = $this->maintenanceWindow();
        // Buchhaltung liest den Verleih, pflegt ihn aber nicht.
        $reader = $this->userWithRole(\App\Enums\User\UserRole::Buchhaltung->value);

        $this->actingAs($reader)->get(route('rental.calendar', ['month' => '2026-10']))
            ->assertOk()
            ->assertSee('Ölwechsel')
            ->assertDontSee('action="' . route('rental.reservations.cancel', $window) . '"', false);
        $this->actingAs($reader)->post(route('rental.reservations.cancel', $window))->assertForbidden();
        $this->assertSame(RentalReservationStatus::Active, $window->fresh()->status);

        // Fremde Organisation: das Fenster ist nicht adressierbar.
        $otherOrg = \App\Models\Platform\Organization::factory()->create();
        $foreign = RentalReservation::query()->create([
            'organization_id' => $otherOrg->id,
            'asset_id' => Asset::factory()->create(['organization_id' => $otherOrg->id])->id,
            'kind' => RentalReservationKind::Maintenance,
            'status' => RentalReservationStatus::Active,
            'starts_at' => '2026-10-12 08:00',
            'ends_at' => '2026-10-12 16:00',
        ]);
        $this->actingAs($this->admin)->post(route('rental.reservations.cancel', $foreign))->assertNotFound();
        $this->assertSame(RentalReservationStatus::Active, RentalReservation::query()->withoutGlobalScopes()->findOrFail($foreign->id)->status);
    }

    // ── Checklistenpositionen ────────────────────────────────────────────

    public function test_condition_items_accept_exactly_the_known_states(): void {
        $case = $this->reservedCase();
        $payload = fn (array $items): array => ['asset_id' => $this->excavator->sqid, 'condition' => 'good', 'condition_items' => $items];

        foreach (['kaputt', 'good', 'OK'] as $rejected) {
            $this->actingAs($this->admin)->post(route('rental.handover', $case), $payload([['label' => 'Schaufel', 'state' => $rejected]]))
                ->assertSessionHasErrors('condition_items.0.state');
        }
        $this->assertSame(0, RentalConditionItem::query()->count());

        $this->assertSame(['ok', 'worn', 'damaged', 'missing'], array_column(RentalConditionItemState::cases(), 'value'));
        $items = array_map(static fn (RentalConditionItemState $state): array => ['label' => 'Teil ' . $state->value, 'state' => $state->value], RentalConditionItemState::cases());
        $this->actingAs($this->admin)->post(route('rental.handover', $case), $payload([...$items, ['label' => 'Ohne Befund']]))->assertSessionHasNoErrors();

        $stored = RentalConditionItem::query()->orderBy('id')->get()->map(fn (RentalConditionItem $item): RentalConditionItemState => $item->state)->all();
        $this->assertSame([...RentalConditionItemState::cases(), RentalConditionItemState::Ok], $stored);
    }

    public function test_service_stores_unknown_condition_states_as_ok(): void {
        $service = app(RentalCaseService::class);
        $case = $this->reservedCase();

        $service->handover($case->fresh(), $this->excavator, $this->admin, ['condition' => 'good', 'condition_items' => [
            ['label' => 'Schaufel', 'state' => 'unbekannt'],
            ['label' => 'Kette', 'state' => 7],
            ['label' => 'Sitz'],
            ['label' => 'Spiegel', 'state' => 'missing'],
        ]]);

        $this->assertSame(['ok', 'ok', 'ok', 'missing'], RentalConditionItem::query()->orderBy('id')->toBase()->pluck('state')->all());
    }
}
