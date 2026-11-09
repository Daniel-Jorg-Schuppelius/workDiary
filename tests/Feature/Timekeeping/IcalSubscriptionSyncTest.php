<?php
/*
 * Created on   : Mon Nov 09 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : IcalSubscriptionSyncTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Timekeeping;

use App\Enums\Import\ImportEntity;
use App\Models\Integration\{ExternalReference, IntegrationInboxItem};
use App\Models\Platform\User;
use App\Models\Project\Project;
use App\Models\Time\{IcalSubscription, TimeEntry};
use App\Services\Timekeeping\IcalSubscriptionSync;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Psr\Http\Message\RequestInterface;
use Tests\Concerns\WithOrganization;
use Tests\Support\FakePluginHttp;
use Tests\TestCase;

/**
 * iCal-Import als Dauer-Sync (Feature 094, MVP-1516): inkrementell über UID,
 * SEQUENCE und LAST-MODIFIED, Serien über das common-toolkit, Löschungen der
 * Quelle als Hinweis, kein Doppel zum Einmalimport, SSRF-Schranke.
 */
class IcalSubscriptionSyncTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private const string FEED = 'https://kalender.example.test/team.ics';

    private User $person;

    private Project $project;

    protected function setUp(): void {
        parent::setUp();
        $this->travelTo(Carbon::parse('2026-11-09 12:00:00', 'UTC'));
        $this->setUpOrganization(['timezone' => 'Europe/Berlin']);
        $this->person = $this->orgUser(['email' => 'erika.beispiel@example.org']);
        $this->project = Project::factory()->create(['organization_id' => $this->organization->id, 'name' => 'Website Relaunch']);
    }

    /** @param array<string, mixed> $attributes */
    private function subscription(array $attributes = []): IcalSubscription {
        $subscription = new IcalSubscription;
        $subscription->forceFill($attributes + [
            'organization_id' => $this->organization->id,
            'user_id' => $this->person->id,
            'import_entity' => ImportEntity::ProjectTimes,
            'name' => 'Teamkalender',
            'url' => self::FEED,
            'is_active' => true,
        ])->save();

        return $subscription;
    }

    /** @param list<string> $events */
    private function ics(array $events): string {
        return "BEGIN:VCALENDAR\r\nVERSION:2.0\r\nPRODID:-//Test//EN\r\n" . implode('', $events) . "END:VCALENDAR\r\n";
    }

    private function event(string $uid, string $start, string $end, string $summary = 'Website Relaunch', int $sequence = 0, string $extra = ''): string {
        $modified = sprintf('202611%02dT080000Z', $sequence + 1);

        return "BEGIN:VEVENT\r\nUID:{$uid}\r\nSEQUENCE:{$sequence}\r\nLAST-MODIFIED:{$modified}\r\n"
            . "DTSTART:{$start}\r\nDTEND:{$end}\r\nSUMMARY:{$summary}\r\nDESCRIPTION:Abstimmung Startseite\r\n{$extra}END:VEVENT\r\n";
    }

    private function serve(string $ics, array $headers = []): FakePluginHttp {
        return FakePluginHttp::fake([self::FEED => FakePluginHttp::response($ics, 200, $headers + ['Content-Type' => 'text/calendar'])]);
    }

    public function test_books_only_past_events_in_the_lookback_window_and_expands_series(): void {
        $this->serve($this->ics([
            $this->event('past-1', '20261105T080000Z', '20261105T093000Z'),
            $this->event('too-old', '20260901T080000Z', '20260901T090000Z'),
            $this->event('later-today', '20261109T150000Z', '20261109T160000Z'),
            // Wöchentlich montags 07:00–08:00 UTC: 26.10., 02.11. und 09.11. liegen im Rückblick und sind vorbei.
            $this->event('weekly', '20261026T070000Z', '20261026T080000Z', extra: "RRULE:FREQ=WEEKLY;COUNT=10\r\n"),
        ]));

        $counts = app(IcalSubscriptionSync::class)->sync($this->subscription());

        $this->assertSame(4, $counts['created']);
        $entries = TimeEntry::query()->where('user_id', $this->person->id)->orderBy('started_at')->get();
        $this->assertCount(4, $entries);
        $this->assertTrue($entries->every(fn (TimeEntry $e): bool => (int) $e->project_id === $this->project->id));
        $this->assertSame(90, (int) TimeEntry::query()->whereDate('date', '2026-11-05')->sole()->minutes);
    }

    public function test_second_run_is_incremental_and_changes_update_the_booking(): void {
        $subscription = $this->subscription();
        $this->serve($this->ics([$this->event('past-1', '20261105T080000Z', '20261105T093000Z')]));
        app(IcalSubscriptionSync::class)->sync($subscription);

        $again = app(IcalSubscriptionSync::class)->sync($subscription->refresh());
        $this->assertSame(['created' => 0, 'updated' => 0, 'unchanged' => 1], array_intersect_key($again, ['created' => 0, 'updated' => 0, 'unchanged' => 0]));

        $this->serve($this->ics([$this->event('past-1', '20261105T080000Z', '20261105T100000Z', sequence: 1)]));
        $changed = app(IcalSubscriptionSync::class)->sync($subscription->refresh());

        $this->assertSame(1, $changed['updated']);
        $this->assertSame(1, TimeEntry::query()->count());
        $this->assertSame(120, (int) TimeEntry::query()->sole()->minutes);
    }

    public function test_event_removed_at_the_source_raises_a_hint_and_keeps_the_time(): void {
        $subscription = $this->subscription();
        $this->serve($this->ics([
            $this->event('past-1', '20261105T080000Z', '20261105T093000Z'),
            $this->event('past-2', '20261106T080000Z', '20261106T090000Z'),
        ]));
        app(IcalSubscriptionSync::class)->sync($subscription);

        $this->serve($this->ics([$this->event('past-2', '20261106T080000Z', '20261106T090000Z')]));
        $counts = app(IcalSubscriptionSync::class)->sync($subscription->refresh());

        $this->assertSame(1, $counts['removed']);
        $this->assertSame(2, TimeEntry::query()->count());
        $hint = IntegrationInboxItem::query()->where('plugin_id', IntegrationInboxItem::PLUGIN_ICAL_SUBSCRIPTION)->sole();
        $this->assertSame('Termin in „Teamkalender“ entfernt – gebuchte Zeit prüfen', $hint->display_title);
        $this->assertSame(IntegrationInboxItem::RESOLUTION_ACKNOWLEDGE_ONLY, $hint->remote_snapshot['resolution']);
    }

    public function test_event_already_booked_by_the_file_import_is_adopted_not_duplicated(): void {
        $existing = TimeEntry::factory()->create([
            'organization_id' => $this->organization->id,
            'user_id' => $this->person->id,
            'project_id' => $this->project->id,
            'date' => '2026-11-05',
            'minutes' => 75,
            'description' => 'von Hand korrigiert',
        ]);
        ExternalReference::query()->create([
            'organization_id' => $this->organization->id,
            'plugin_id' => 'time-import',
            'external_type' => 'project-time',
            'referenceable_type' => $existing->getMorphClass(),
            'referenceable_id' => $existing->id,
            'external_id' => 'ext:past-1',
        ]);
        $this->serve($this->ics([$this->event('past-1', '20261105T080000Z', '20261105T093000Z')]));

        $counts = app(IcalSubscriptionSync::class)->sync($this->subscription());

        $this->assertSame(1, $counts['adopted']);
        $this->assertSame(1, TimeEntry::query()->count());
        $this->assertSame('von Hand korrigiert', $existing->refresh()->description);
    }

    public function test_conditional_request_skips_an_unchanged_feed(): void {
        $subscription = $this->subscription();
        $this->serve($this->ics([$this->event('past-1', '20261105T080000Z', '20261105T093000Z')]), ['ETag' => '"rev-7"']);
        app(IcalSubscriptionSync::class)->sync($subscription);
        $this->assertSame('"rev-7"', $subscription->refresh()->http_etag);

        $fake = FakePluginHttp::fake([self::FEED => FakePluginHttp::response('', 304)]);
        app(IcalSubscriptionSync::class)->sync($subscription);

        $fake->assertSent(fn (RequestInterface $request): bool => $request->getHeaderLine('If-None-Match') === '"rev-7"');
        $this->assertSame(\App\Enums\Import\IcalSyncStatus::NotModified, $subscription->refresh()->last_status);
    }

    public function test_private_address_is_refused_without_opt_in(): void {
        $fake = FakePluginHttp::fake();
        $subscription = $this->subscription(['url' => 'http://127.0.0.1/kalender.ics']);

        app(IcalSubscriptionSync::class)->sync($subscription);

        $fake->assertNothingSent();
        $this->assertSame(\App\Enums\Import\IcalSyncStatus::Failed, $subscription->refresh()->last_status);
        $this->assertSame(0, TimeEntry::query()->count());
    }

    public function test_command_syncs_active_subscriptions(): void {
        $this->subscription();
        $this->subscription(['name' => 'Pausiert', 'is_active' => false]);
        $this->serve($this->ics([$this->event('past-1', '20261105T080000Z', '20261105T093000Z')]));

        $this->artisan('ical:sync')
            ->expectsOutputToContain('iCal-Abos: 1 neu')
            ->assertSuccessful();
    }

    public function test_editing_keeps_the_stored_address_and_fetch_now_reports_the_result(): void {
        $admin = $this->orgAdmin();
        $subscription = $this->subscription();

        $this->actingAs($admin)
            ->put(route('admin.imports.ical-subscriptions.update', $subscription), [
                'name' => 'Teamkalender neu',
                'import_entity' => ImportEntity::ProjectTimes->value,
                'url' => '',
                'user_id' => $this->person->sqid,
                'is_active' => '1',
            ])
            ->assertSessionHasNoErrors();
        $this->assertSame(self::FEED, $subscription->refresh()->url);
        $this->assertSame('Teamkalender neu', $subscription->name);

        $this->serve($this->ics([$this->event('past-1', '20261105T080000Z', '20261105T093000Z')]));
        $this->actingAs($admin)
            ->post(route('admin.imports.ical-subscriptions.sync', $subscription))
            ->assertSessionHas('success', 'Abgerufen: 1 neu, 0 geändert, 0 aus dem Datei-Import übernommen, 0 in der Inbox, 0 fehlerhaft, 0 in der Quelle entfernt.');
    }

    public function test_admin_subscribes_a_calendar_with_webcal_address(): void {
        $admin = $this->orgAdmin();

        $this->actingAs($admin)
            ->post(route('admin.imports.ical-subscriptions.store'), [
                'name' => 'Outlook Erika',
                'import_entity' => ImportEntity::ProjectTimes->value,
                'url' => 'webcal://kalender.example.test/erika.ics',
                'user_id' => $this->person->sqid,
                'category_allowlist' => 'Projekt, Kunde',
                'is_active' => '1',
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect();

        $subscription = IcalSubscription::query()->sole();
        $this->assertSame('https://kalender.example.test/erika.ics', $subscription->url);
        $this->assertSame(['Projekt', 'Kunde'], $subscription->category_allowlist);
        $this->assertStringNotContainsString('erika.ics', (string) $subscription->getRawOriginal('url'));

        $this->actingAs($admin)
            ->get(route('admin.imports.ical-subscriptions.index'))
            ->assertOk()
            ->assertSee('Outlook Erika')
            ->assertSee('kalender.example.test')
            ->assertDontSee('erika.ics');

        $this->actingAs($admin)
            ->post(route('admin.imports.ical-subscriptions.store'), [
                'name' => 'Intern',
                'import_entity' => ImportEntity::ProjectTimes->value,
                'url' => 'http://10.0.0.5/kalender.ics',
            ])
            ->assertSessionHasErrors(['url' => 'Die Adresse zeigt in ein internes Netz. Für einen eigenen Kalenderserver „Kalender im eigenen Netz erlauben“ aktivieren.']);
    }
}
