<?php
/*
 * Created on   : Mon Nov 09 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : IcalRemovedEventInboxTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Feature\Timekeeping;

use App\Enums\Import\ImportEntity;
use App\Enums\Integration\IntegrationInboxStatus;
use App\Enums\User\UserRole;
use App\Models\Integration\IntegrationInboxItem;
use App\Models\Platform\User;
use App\Models\Project\Project;
use App\Models\Time\{IcalSubscription, TimeEntry};
use App\Services\Timekeeping\IcalSubscriptionSync;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\Concerns\WithOrganization;
use Tests\Support\FakePluginHttp;
use Tests\TestCase;

/**
 * Verschwundener Kalendertermin in der Zuordnungs-Inbox (MVP-1528, nl-07):
 * statt „Remote übernehmen“ (scheiterte immer mit „bereits abgerechnet“)
 * Zeiteintrag öffnen, löschen oder den Hinweis zur Kenntnis nehmen.
 */
final class IcalRemovedEventInboxTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private const string FEED = 'https://kalender.example.test/werkstatt.ics';

    private User $admin;

    protected function setUp(): void {
        parent::setUp();
        $this->travelTo(Carbon::parse('2026-11-09 12:00:00', 'UTC'));
        $this->setUpOrganization(['timezone' => 'Europe/Berlin']);
        $this->admin = $this->orgAdmin(['email' => 'erika.beispiel@example.org']);
        Project::factory()->create(['organization_id' => $this->organization->id, 'name' => 'Werkstattumbau']);
    }

    /** Bucht zwei Termine über das Abo und entfernt danach einen in der Quelle (ein leerer Kalender gilt nicht als Löschung). */
    private function removedEvent(): IntegrationInboxItem {
        $subscription = new IcalSubscription;
        $subscription->forceFill([
            'organization_id' => $this->organization->id,
            'user_id' => $this->admin->id,
            'import_entity' => ImportEntity::ProjectTimes,
            'name' => 'Werkstattkalender',
            'url' => self::FEED,
            'is_active' => true,
        ])->save();

        $event = static fn (string $uid, string $day): string => "BEGIN:VEVENT\r\nUID:{$uid}\r\nSEQUENCE:0\r\nLAST-MODIFIED:20261101T080000Z\r\n"
            . "DTSTART:202611{$day}T080000Z\r\nDTEND:202611{$day}T093000Z\r\nSUMMARY:Werkstattumbau\r\nEND:VEVENT\r\n";
        $calendar = static fn (string $events): string => "BEGIN:VCALENDAR\r\nVERSION:2.0\r\nPRODID:-//Test//EN\r\n{$events}END:VCALENDAR\r\n";

        FakePluginHttp::fake([self::FEED => FakePluginHttp::response($calendar($event('umbau-1', '05') . $event('umbau-2', '06')), 200, ['Content-Type' => 'text/calendar'])]);
        app(IcalSubscriptionSync::class)->sync($subscription);
        FakePluginHttp::fake([self::FEED => FakePluginHttp::response($calendar($event('umbau-2', '06')), 200, ['Content-Type' => 'text/calendar'])]);
        app(IcalSubscriptionSync::class)->sync($subscription->refresh());
        $this->assertSame(2, TimeEntry::query()->count());

        $item = IntegrationInboxItem::query()->where('plugin_id', IntegrationInboxItem::PLUGIN_ICAL_SUBSCRIPTION)->sole();
        $this->assertTrue($item->isRemoteDeletion());

        return $item;
    }

    public function test_inbox_offers_open_delete_and_acknowledge_instead_of_taking_over_remote(): void {
        $item = $this->removedEvent();
        $entry = $item->referenceable;
        $this->assertInstanceOf(TimeEntry::class, $entry);

        $this->actingAs($this->admin)->get(route('admin.integration.inbox'))->assertOk()
            ->assertSee('Termin in „Werkstattkalender“ entfernt – gebuchte Zeit prüfen')
            ->assertSee('Zeiteintrag öffnen')
            ->assertSee(route('projects.time-entries.edit', [$entry->project, $entry]), false)
            ->assertSee('Zeiteintrag löschen')
            ->assertSee('Zur Kenntnis nehmen')
            ->assertDontSee('Remote übernehmen')
            ->assertDontSee(route('admin.integration.inbox.accept-remote', $item), false);
    }

    public function test_delete_removes_the_time_entry_and_resolves_the_notice(): void {
        $item = $this->removedEvent();

        $this->actingAs($this->admin)->from(route('admin.integration.inbox'))
            ->post(route('admin.integration.inbox.delete-record', $item))
            ->assertRedirect(route('admin.integration.inbox'))
            ->assertSessionHas('success', 'Zeiteintrag gelöscht, der Hinweis ist erledigt.');

        $this->assertNull(TimeEntry::query()->find($item->referenceable_id));
        $this->assertSame(1, TimeEntry::query()->count(), 'nur die Zeit des entfernten Termins');
        $this->assertSame(IntegrationInboxStatus::ResolvedRemote, $item->refresh()->status);
    }

    public function test_billed_time_stays_and_keeps_the_billed_message(): void {
        $item = $this->removedEvent();
        TimeEntry::query()->findOrFail($item->referenceable_id)->forceFill(['exported' => true])->saveQuietly();

        $this->actingAs($this->admin)->get(route('admin.integration.inbox'))->assertOk()
            ->assertDontSee('Zeiteintrag löschen')
            ->assertSee('Zur Kenntnis nehmen');

        $this->actingAs($this->admin)->from(route('admin.integration.inbox'))
            ->post(route('admin.integration.inbox.delete-record', $item))
            ->assertSessionHas('error', 'Dieser Datensatz ist bereits abgerechnet — der Fremdstand kann nicht übernommen werden. Bitte den Fall zur Kenntnis nehmen und die Abweichung über eine Korrektur buchen.');

        $this->assertSame(2, TimeEntry::query()->count());
        $this->assertTrue($item->refresh()->isOpen());
    }

    public function test_deleting_needs_the_right_of_the_normal_deletion(): void {
        $item = $this->removedEvent();
        $accounting = $this->userWithRole(UserRole::Buchhaltung->value);

        $this->actingAs($accounting)->get(route('admin.integration.inbox'))->assertOk()
            ->assertSee('Zur Kenntnis nehmen')
            ->assertDontSee('Zeiteintrag löschen')
            ->assertDontSee('Zeiteintrag öffnen');

        $this->actingAs($accounting)->post(route('admin.integration.inbox.delete-record', $item))->assertForbidden();
        $this->assertSame(2, TimeEntry::query()->count());
    }

    public function test_acknowledge_keeps_the_time_and_take_over_names_the_real_reason(): void {
        $item = $this->removedEvent();

        $this->actingAs($this->admin)->from(route('admin.integration.inbox'))
            ->post(route('admin.integration.inbox.accept-remote', $item))
            ->assertSessionHas('error', 'Der Termin ist in der Quelle entfernt, es gibt keinen Stand zum Übernehmen. Löschen Sie den Zeiteintrag oder nehmen Sie den Hinweis zur Kenntnis.');

        $this->actingAs($this->admin)->from(route('admin.integration.inbox'))
            ->post(route('admin.integration.inbox.keep-local', $item))
            ->assertSessionHas('success', 'Hinweis zur Kenntnis genommen, die gebuchte Zeit bleibt.');

        $this->assertSame(IntegrationInboxStatus::ResolvedLocal, $item->refresh()->status);
        $this->assertSame(2, TimeEntry::query()->count());
    }
}
