<?php
/*
 * Created on   : Tue Oct 06 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : FritzboxDismissedCallTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Plugins;

use App\Enums\Integration\IntegrationInboxStatus;
use App\Models\Integration\IntegrationInboxItem;
use App\Models\Platform\{Organization, User};
use App\Plugins\Fritzbox\FritzboxPlugin;
use App\Plugins\Fritzbox\Models\FritzboxDismissedCall;
use App\Plugins\Fritzbox\Services\{FritzboxGroupBooker, FritzboxImportService, FritzboxSuggestionService};
use App\Plugins\Fritzbox\Sources\FritzboxCall;
use App\Services\Org\OrganizationLifecycleService;
use Carbon\CarbonImmutable;
use CommonToolkit\Helper\Data\CryptoHelper;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/**
 * Entscheidung 2026-10-06: verworfene FRITZ!Box-Anrufe hinterlassen eine
 * Sperrmarke ohne Personenbezug. Der Fall selbst wird nach der Frist
 * aufgeräumt; der Anruf steht trotzdem nie wieder im Eingang, auch wenn die
 * Anrufliste der Box oder ein alter CSV-Export ihn erneut liefert.
 */
class FritzboxDismissedCallTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private const NUMBER = '+492219567000';

    private User $owner;

    protected function setUp(): void {
        parent::setUp();
        $this->travelTo('2026-08-01 10:00:00');
        $this->setUpOrganization();

        $this->owner = User::factory()->create(['organization_id' => $this->organization->id]);
        $this->organization->forceFill(['owner_id' => $this->owner->id])->save();
    }

    private function service(): FritzboxImportService {
        return new FritzboxImportService;
    }

    private function booker(): FritzboxGroupBooker {
        return new FritzboxGroupBooker($this->service(), new FritzboxSuggestionService);
    }

    /** @return array<string, mixed> */
    private function config(): array {
        return [
            'default_billable' => true,
            'default_user_id' => null,
            'min_call_minutes' => 2,
            'call_lead_minutes' => 15,
            'own_number_allowlist' => [],
            'type3_outgoing' => false,
        ];
    }

    private function makeCall(string $start, int $minutes = 10): FritzboxCall {
        $startedAt = CarbonImmutable::parse($start, 'UTC');

        return new FritzboxCall(
            type: FritzboxCall::TYPE_INCOMING,
            direction: FritzboxCall::DIR_IN,
            startedAt: $startedAt,
            endedAt: $startedAt->addMinutes($minutes),
            durationMinutes: $minutes,
            numberRaw: '02219567000',
            e164: self::NUMBER,
            name: null,
            ownLine: '97911585',
        );
    }

    /** @return 'created'|'linked'|'skipped'|'ignored'|'pending'|'locked' */
    private function book(string $start, ?Organization $organization = null): string {
        return $this->service()->bookCall($organization ?? $this->organization, $this->config(), $this->makeCall($start), $this->owner->id);
    }

    private function stage(string ...$starts): void {
        foreach ($starts as $start) {
            $this->assertSame('pending', $this->book($start));
        }
    }

    private function hashOf(string $start, ?int $organizationId = null): string {
        return FritzboxDismissedCall::hashFor($organizationId ?? (int) $this->organization->id, $this->makeCall($start)->callKey());
    }

    /** @return Collection<int, FritzboxDismissedCall> */
    private function marks(?Organization $organization = null): Collection {
        return FritzboxDismissedCall::query()
            ->withoutGlobalScopes()
            ->when($organization !== null, fn ($query) => $query->where('organization_id', $organization->id))
            ->orderBy('id')
            ->get();
    }

    private function openItems(): int {
        return IntegrationInboxItem::query()->where('status', IntegrationInboxStatus::Open)->count();
    }

    public function test_dismissing_a_group_leaves_one_mark_per_call_without_plaintext(): void {
        $this->stage('2026-07-20 09:00:00', '2026-07-21 10:00:00');
        $this->actingAs($this->owner);

        $this->assertSame(2, $this->booker()->dismiss($this->organization, self::NUMBER));

        $marks = $this->marks();
        $this->assertCount(2, $marks);
        $this->assertEqualsCanonicalizing(
            [$this->hashOf('2026-07-20 09:00:00'), $this->hashOf('2026-07-21 10:00:00')],
            $marks->pluck('call_hash')->all(),
        );
        foreach ($marks as $mark) {
            $this->assertSame((int) $this->organization->id, (int) $mark->organization_id);
            $this->assertTrue(now()->equalTo($mark->dismissed_at));
            // Kein Klartext: weder Rufnummer noch der Anrufschlüssel selbst.
            $this->assertStringNotContainsString('2219567000', $mark->call_hash);
            $this->assertStringNotContainsString('call:', $mark->call_hash);
            foreach (['2026-07-20 09:00:00', '2026-07-21 10:00:00'] as $start) {
                $this->assertStringNotContainsString(substr($this->makeCall($start)->callKey(), 5), $mark->call_hash);
            }
            $this->assertEqualsCanonicalizing(['id', 'organization_id', 'call_hash', 'dismissed_at'], array_keys($mark->getAttributes()), 'Die Marke trägt nur Organisation, Hash und Zeitpunkt — keinen Benutzer.');
        }
        $this->assertSame(0, $this->openItems());
    }

    public function test_the_mark_is_an_hmac_of_the_call_key_under_the_app_key_and_differs_per_organization(): void {
        $callKey = $this->makeCall('2026-07-20 09:00:00')->callKey();

        $this->assertSame(
            CryptoHelper::createHmac($this->organization->id . '|' . $callKey, (string) config('app.key')),
            FritzboxDismissedCall::hashFor((int) $this->organization->id, $callKey),
        );
        $this->assertNotSame(
            FritzboxDismissedCall::hashFor((int) $this->organization->id, $callKey),
            FritzboxDismissedCall::hashFor((int) $this->organization->id + 1, $callKey),
        );

        FritzboxDismissedCall::mark((int) $this->organization->id, $callKey);
        FritzboxDismissedCall::mark((int) $this->organization->id, $callKey);
        $this->assertCount(1, $this->marks(), 'Die Marke ist idempotent.');
    }

    public function test_dismissing_a_single_call_of_a_shared_number_marks_only_that_call(): void {
        $this->stage('2026-07-20 09:00:00', '2026-07-21 10:00:00');
        $this->booker()->book($this->organization, self::NUMBER, ['action' => 'shared']);
        $single = (string) $this->booker()->groups($this->organization)->firstOrFail()['group_key'];

        $this->assertSame(1, $this->booker()->dismiss($this->organization, $single));

        $marks = $this->marks();
        $this->assertCount(1, $marks);
        $this->assertSame(FritzboxDismissedCall::hashFor((int) $this->organization->id, explode('|', $single, 2)[1]), $marks->first()->call_hash);
        $this->assertSame(1, $this->openItems());
    }

    public function test_ignoring_a_number_marks_every_dismissed_call(): void {
        $this->stage('2026-07-20 09:00:00', '2026-07-21 10:00:00');
        $this->booker()->book($this->organization, self::NUMBER, ['action' => 'shared']);
        $this->stage('2026-07-22 11:00:00'); // Einzelgruppe der geteilten Nummer

        $result = $this->booker()->book($this->organization, self::NUMBER, ['action' => 'ignore']);

        $this->assertSame(3, $result['skipped']);
        $this->assertEqualsCanonicalizing(
            [$this->hashOf('2026-07-20 09:00:00'), $this->hashOf('2026-07-21 10:00:00'), $this->hashOf('2026-07-22 11:00:00')],
            $this->marks()->pluck('call_hash')->all(),
        );
        // Die Ignorierliste bleibt, wie sie ist: künftige Anrufe der Nummer fallen weiterhin als ignoriert raus.
        $this->assertSame('ignored', $this->book('2026-07-25 09:00:00'));
    }

    public function test_after_the_purge_a_dismissed_call_is_skipped_but_a_new_call_of_the_number_is_pending(): void {
        $this->stage('2026-07-20 09:00:00', '2026-07-21 10:00:00');
        $this->actingAs($this->owner);
        $this->booker()->dismiss($this->organization, self::NUMBER);

        $this->travel(91)->days();
        Artisan::call('integration:purge-inbox');
        $this->assertSame(0, IntegrationInboxItem::query()->count(), 'Der Fall selbst wird aufgeräumt.');
        $this->assertCount(2, $this->marks(), 'Die Marke überdauert das Aufräumen.');

        // Derselbe Anruf aus der Anrufliste der Box: übersprungen, kein neuer Fall.
        $this->assertSame('skipped', $this->book('2026-07-20 09:00:00'));
        $this->assertSame('skipped', $this->book('2026-07-21 10:00:00'));
        $this->assertSame(0, IntegrationInboxItem::query()->count());

        // Ein anderer Anruf derselben Nummer steht wieder zur Zuordnung.
        $this->assertSame('pending', $this->book('2026-07-22 11:00:00'));
        $this->assertSame(1, $this->openItems());

        // Auch als geteilte Nummer (Einzelzuordnung) bleibt der verworfene Anruf gesperrt.
        $this->booker()->book($this->organization, self::NUMBER, ['action' => 'shared']);
        $this->assertSame('skipped', $this->book('2026-07-20 09:00:00'));
        $this->assertSame(1, $this->openItems());

        // Der Importlauf zählt gesperrte Anrufe als übersprungen.
        $result = $this->service()->importCalls($this->organization, $this->config(), [
            $this->makeCall('2026-07-20 09:00:00'),
            $this->makeCall('2026-07-21 10:00:00'),
            $this->makeCall('2026-07-23 08:00:00'),
        ]);
        $this->assertSame(2, $result['skipped']);
        $this->assertSame(1, $result['pending']);
    }

    public function test_marks_are_tenant_scoped(): void {
        $this->stage('2026-07-20 09:00:00');
        $this->booker()->dismiss($this->organization, self::NUMBER);
        $other = Organization::factory()->create();

        $this->assertSame('pending', $this->book('2026-07-20 09:00:00', $other));

        $this->assertSame(1, IntegrationInboxItem::query()->withoutGlobalScopes()->where('organization_id', $other->id)->where('status', IntegrationInboxStatus::Open)->count());
        $this->assertSame('skipped', $this->book('2026-07-20 09:00:00'));
    }

    /**
     * Altbestand: heute verworfene Fälle tragen keine Marke. Die Migration
     * leitet sie aus dem gespeicherten Anrufschlüssel ab — bevor der
     * Aufräumlauf die Fälle löscht.
     */
    public function test_backfill_marks_the_dismissed_cases_of_the_old_stock_once(): void {
        $dismissedAt = Carbon::now()->subDays(10);
        $dismissed = $this->makeCall('2026-07-20 09:00:00')->callKey();
        $open = $this->makeCall('2026-07-21 10:00:00')->callKey();
        $keyOnly = $this->makeCall('2026-07-22 11:00:00')->callKey();
        $this->inboxItem(FritzboxPlugin::ID, 'call', $dismissed, IntegrationInboxStatus::Dismissed, $dismissedAt);
        $this->inboxItem(FritzboxPlugin::ID, 'call', $open, IntegrationInboxStatus::Open, null);
        $this->inboxItem(FritzboxPlugin::ID, 'call', $keyOnly, IntegrationInboxStatus::Dismissed, null, externalId: false);
        $this->inboxItem('toggl', 'entry', 'ext-1', IntegrationInboxStatus::Dismissed, $dismissedAt);
        $this->assertCount(0, $this->marks());

        $migration = require base_path('app/Plugins/Fritzbox/Database/Migrations/2027_03_10_100600_backfill_fritzbox_dismissed_calls.php');
        $migration->up();
        $migration->up(); // idempotent

        $marks = $this->marks()->keyBy('call_hash');
        $this->assertCount(2, $marks);
        $orgId = (int) $this->organization->id;
        $this->assertTrue($dismissedAt->equalTo($marks->get(FritzboxDismissedCall::hashFor($orgId, $dismissed))?->dismissed_at));
        $this->assertTrue(now()->equalTo($marks->get(FritzboxDismissedCall::hashFor($orgId, $keyOnly))?->dismissed_at), 'Ohne resolved_at gilt der Migrationszeitpunkt.');
        $this->assertFalse($marks->has(FritzboxDismissedCall::hashFor($orgId, $open)), 'Offene Fälle bekommen keine Marke.');
        $this->assertFalse($marks->has(FritzboxDismissedCall::hashFor($orgId, 'ext-1')), 'Fremde Plugins bekommen keine Marke.');

        // Nach dem Aufräumen bleibt der alte verworfene Anruf gesperrt, der offene Fall steht weiter.
        $this->travel(91)->days();
        Artisan::call('integration:purge-inbox');
        $this->assertNull(IntegrationInboxItem::query()->where('external_id', $dismissed)->first(), 'Der datierte Fall ist aufgeräumt.');
        $this->assertSame('skipped', $this->book('2026-07-20 09:00:00'));
        $this->assertSame('skipped', $this->book('2026-07-22 11:00:00'));
        $this->assertSame('pending', $this->book('2026-07-21 10:00:00'));
        // Offener Fall und der undatierte (den datiert erst Migration 2027_03_10_100200) bleiben — kein neuer kommt dazu.
        $this->assertSame(2, IntegrationInboxItem::query()->count());
    }

    public function test_purging_the_organization_removes_its_marks_and_spares_the_others(): void {
        $doomed = Organization::factory()->create();
        $keep = Organization::factory()->create();
        FritzboxDismissedCall::mark((int) $doomed->id, 'call:a');
        FritzboxDismissedCall::mark((int) $doomed->id, 'call:b');
        FritzboxDismissedCall::mark((int) $keep->id, 'call:a');

        app(OrganizationLifecycleService::class)->purge($doomed->fresh(), null);

        $this->assertCount(0, $this->marks($doomed));
        $this->assertCount(1, $this->marks($keep));
        $this->assertTrue(FritzboxDismissedCall::covers((int) $keep->id, 'call:a'));
    }

    private function inboxItem(string $pluginId, string $externalType, string $key, IntegrationInboxStatus $status, ?Carbon $resolvedAt, bool $externalId = true): IntegrationInboxItem {
        $item = new IntegrationInboxItem;
        $item->forceFill([
            'organization_id' => $this->organization->id,
            'plugin_id' => $pluginId,
            'source' => 'csv',
            'target_type' => 'time_entries',
            'external_type' => $externalType,
            'external_id' => $externalId ? $key : null,
            'dedupe_key' => $externalType . ':' . $key,
            'group_key' => self::NUMBER,
            'case_type' => IntegrationInboxItem::CASE_UNMATCHED,
            'status' => $status,
            'remote_snapshot' => [],
            'resolved_at' => $resolvedAt,
        ])->save();

        return $item;
    }
}
