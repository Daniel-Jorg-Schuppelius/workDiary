<?php
/*
 * Created on   : Mon Nov 09 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : IcalConnectionSubscriptionTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Feature\Timekeeping;

use App\Enums\Import\{IcalSyncStatus, ImportEntity};
use App\Models\Platform\{Organization, User};
use App\Models\Project\Project;
use App\Models\Time\{IcalSubscription, TimeEntry};
use App\Plugins\CalDav\Models\CalDavConnection;
use App\Services\Timekeeping\IcalSubscriptionSync;
use App\Support\Sqid;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use RuntimeException;
use Tests\Concerns\WithOrganization;
use Tests\Support\{FakeCalendarImportFeed, FakePluginHttp};
use Tests\TestCase;

/**
 * Dauer-Sync aus Kalenderverbindungen (Feature 094, MVP-1531): das Abo liest
 * über den Feed des Einmalimports das iCal-Dokument des Rückblicks und
 * verarbeitet es wie eine abonnierte Adresse; zur Wahl stehen nur die
 * Verbindungen der eigenen Organisation.
 */
final class IcalConnectionSubscriptionTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private User $person;

    private FakeCalendarImportFeed $feed;

    protected function setUp(): void {
        parent::setUp();
        $this->travelTo(Carbon::parse('2026-11-09 12:00:00', 'UTC'));
        $this->setUpOrganization(['timezone' => 'Europe/Berlin']);
        $this->person = $this->orgUser(['email' => 'erika.beispiel@example.org']);
        Project::factory()->create(['organization_id' => $this->organization->id, 'name' => 'Website Relaunch']);

        $this->feed = FakeCalendarImportFeed::register();
    }

    private function subscription(): IcalSubscription {
        $subscription = new IcalSubscription;
        $subscription->forceFill([
            'organization_id' => $this->organization->id,
            'user_id' => $this->person->id,
            'import_entity' => ImportEntity::ProjectTimes,
            'name' => 'Teamkalender',
            'url' => null,
            'calendar_source' => FakeCalendarImportFeed::source($this->organization),
            'is_active' => true,
        ])->save();

        return $subscription;
    }

    /** @param list<array{string, string, string}> $events UID, Beginn, Ende (UTC) */
    private function ics(array $events): string {
        $body = '';
        foreach ($events as [$uid, $start, $end]) {
            $body .= "BEGIN:VEVENT\r\nUID:{$uid}\r\nSEQUENCE:0\r\nLAST-MODIFIED:20261101T080000Z\r\n"
                . "DTSTART:{$start}\r\nDTEND:{$end}\r\nSUMMARY:Website Relaunch\r\nEND:VEVENT\r\n";
        }

        return "BEGIN:VCALENDAR\r\nVERSION:2.0\r\nPRODID:-//Test//EN\r\n{$body}END:VCALENDAR\r\n";
    }

    public function test_subscription_reads_the_lookback_window_from_the_calendar_connection(): void {
        $http = FakePluginHttp::fake();
        $subscription = $this->subscription();
        $this->feed->ics = $this->ics([
            ['past-1', '20261105T080000Z', '20261105T093000Z'],
            ['past-2', '20261106T080000Z', '20261106T090000Z'],
        ]);

        $counts = app(IcalSubscriptionSync::class)->sync($subscription);

        $this->assertSame(2, $counts['created']);
        $this->assertSame(90, (int) TimeEntry::query()->whereDate('date', '2026-11-05')->sole()->minutes);
        $call = $this->feed->calls[0];
        $this->assertSame([$this->organization->id, 'team-' . $this->organization->id], [$call['organization'], $call['connection']]);
        $this->assertSame(['2026-10-09T22:00:00+00:00', '2026-11-09T12:00:00+00:00'], [$call['from']->format(DATE_ATOM), $call['until']->format(DATE_ATOM)], '30 Tage Rückblick ab Tagesbeginn der Organisation bis jetzt.');
        $http->assertNothingSent();

        $again = app(IcalSubscriptionSync::class)->sync($subscription->refresh());
        $this->assertSame([0, 2], [$again['created'], $again['unchanged']]);

        $this->feed->ics = $this->ics([['past-2', '20261106T080000Z', '20261106T090000Z']]);
        $removed = app(IcalSubscriptionSync::class)->sync($subscription->refresh());
        $this->assertSame(1, $removed['removed']);
        $this->assertSame(2, TimeEntry::query()->count(), 'Gebuchte Zeit bleibt stehen.');
        $this->assertSame(IcalSyncStatus::Ok, $subscription->refresh()->last_status);
    }

    public function test_unreadable_connection_fails_without_details(): void {
        $subscription = $this->subscription();
        $this->feed->failure = new RuntimeException('Token abgelaufen: geheim-4711');

        app(IcalSubscriptionSync::class)->sync($subscription);

        $subscription->refresh();
        $this->assertSame(IcalSyncStatus::Failed, $subscription->last_status);
        $this->assertSame('Der Kalender konnte nicht abgerufen werden. Bitte die Verbindung prüfen.', $subscription->last_error);
        $this->assertSame(0, TimeEntry::query()->count());
    }

    public function test_admin_chooses_only_calendar_connections_of_the_own_organization(): void {
        $admin = $this->orgAdmin();
        $own = CalDavConnection::query()->create([
            'organization_id' => $this->organization->id, 'name' => 'Nextcloud Team', 'base_url' => 'https://cloud.example.test/remote.php/dav',
            'username' => 'svc', 'app_password' => 'secret', 'calendar_path' => 'calendars/team/zeiten', 'active' => true,
        ]);
        $foreignOrganization = Organization::factory()->create();
        $foreign = CalDavConnection::query()->withoutGlobalScopes()->create([
            'organization_id' => $foreignOrganization->id, 'name' => 'Fremdkalender Nord', 'base_url' => 'https://dav.example.test',
            'username' => 'x', 'app_password' => 'y', 'calendar_path' => 'calendars/x/y', 'active' => true,
        ]);
        $ownSource = 'caldav:' . Sqid::encode(CalDavConnection::class, $own->id);

        $this->actingAs($admin)->get(route('admin.imports.ical-subscriptions.create'))
            ->assertOk()
            ->assertSee('CalDAV · Nextcloud Team')
            ->assertSee($ownSource, false)
            ->assertDontSee('Fremdkalender Nord');

        $this->actingAs($admin)->post(route('admin.imports.ical-subscriptions.store'), [
            'name' => 'Teamkalender', 'import_entity' => ImportEntity::ProjectTimes->value,
            'calendar_source' => 'caldav:' . Sqid::encode(CalDavConnection::class, $foreign->id), 'is_active' => '1',
        ])->assertSessionHasErrors(['calendar_source' => 'Diese Kalenderverbindung steht nicht zur Verfügung. Bitte wählen Sie eine andere Quelle.']);
        $this->assertSame(0, IcalSubscription::query()->count());

        $this->actingAs($admin)->post(route('admin.imports.ical-subscriptions.store'), [
            'name' => 'Teamkalender', 'import_entity' => ImportEntity::ProjectTimes->value,
            'calendar_source' => $ownSource, 'url' => 'https://kalender.example.test/team.ics', 'is_active' => '1',
        ])->assertSessionHasNoErrors();
        $subscription = IcalSubscription::query()->sole();
        $this->assertSame([$ownSource, null], [$subscription->calendar_source, $subscription->url], 'Mit Verbindung entfällt die Adresse.');

        $this->actingAs($admin)->get(route('admin.imports.ical-subscriptions.index'))
            ->assertOk()
            ->assertSee('CalDAV · Nextcloud Team');

        $own->forceFill(['active' => false])->save();
        $this->actingAs($admin)->get(route('admin.imports.ical-subscriptions.index'))
            ->assertSee('Kalenderverbindung nicht mehr verfügbar');
    }

    public function test_switching_back_to_an_address_requires_one(): void {
        $admin = $this->orgAdmin();
        $subscription = $this->subscription();
        $subscription->forceFill(['http_etag' => '"alt"'])->save();
        $payload = ['name' => 'Teamkalender', 'import_entity' => ImportEntity::ProjectTimes->value, 'calendar_source' => '', 'url' => '', 'is_active' => '1'];

        $this->actingAs($admin)->put(route('admin.imports.ical-subscriptions.update', $subscription), $payload)
            ->assertSessionHasErrors('url');

        $this->actingAs($admin)->put(route('admin.imports.ical-subscriptions.update', $subscription), ['url' => 'webcal://kalender.example.test/team.ics'] + $payload)
            ->assertSessionHasNoErrors();
        $subscription->refresh();
        $this->assertSame(['https://kalender.example.test/team.ics', null, null], [$subscription->url, $subscription->calendar_source, $subscription->http_etag]);
    }
}
