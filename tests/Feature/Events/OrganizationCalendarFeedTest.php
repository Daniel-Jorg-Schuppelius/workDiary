<?php
/*
 * Created on   : Sun Sep 13 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : OrganizationCalendarFeedTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Events;

use App\Enums\Event\EventVisibility;
use App\Models\Calendar\Event;
use App\Models\Platform\{Organization, User};
use App\Services\Event\OrganizationCalendarFeedService;
use App\Support\OrganizationContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/**
 * Abo-Link für den gemeinsamen Kalender (Mandanten-Review 2026-09-13).
 *
 * Vorher lag derselbe Inhalt unter der festen Adresse `calendar/public.ics`:
 * ohne Anmeldung UND über alle Mandanten hinweg. Hinter die Anmeldung stellen
 * ließ er sich nicht, weil Outlook/Google/Apple eine Abo-Adresse ohne Sitzung
 * abrufen. Jetzt entscheidet ein zufälliger Token, wessen Termine ausgeliefert
 * werden — und ob überhaupt.
 */
class OrganizationCalendarFeedTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private function feeds(): OrganizationCalendarFeedService {
        return app(OrganizationCalendarFeedService::class);
    }

    private function publicEvent(Organization $organization, User $responsible, string $title): Event {
        return OrganizationContext::run($organization, fn (): Event => Event::factory()->create([
            'organization_id' => $organization->id,
            'responsible_user_id' => $responsible->id,
            'visibility' => EventVisibility::Public,
            'title' => $title,
        ]));
    }

    public function test_the_token_decides_whose_events_are_served(): void {
        $this->setUpOrganization();
        $own = $this->orgAdmin();
        $this->publicEvent($this->organization, $own, 'EIGENER-OEFFENTLICHER-TERMIN');

        $foreign = Organization::factory()->create();
        $foreignUser = User::factory()->user()->create(['organization_id' => $foreign->id]);
        $this->publicEvent($foreign, $foreignUser, 'FREMDER-OEFFENTLICHER-TERMIN');

        $token = $this->feeds()->issue($this->organization);
        app()->forgetInstance('currentOrganization');

        $response = $this->get(route('calendar.feed.organization', ['token' => $token]));

        $response->assertOk();
        $body = (string) $response->getContent();
        $this->assertStringContainsString('EIGENER-OEFFENTLICHER-TERMIN', $body);
        $this->assertStringNotContainsString('FREMDER-OEFFENTLICHER-TERMIN', $body, 'Der Feed darf keine fremden Mandanten mehr enthalten.');
    }

    public function test_internal_events_never_reach_the_feed(): void {
        $this->setUpOrganization();
        $admin = $this->orgAdmin();
        OrganizationContext::run($this->organization, fn () => Event::factory()->create([
            'organization_id' => $this->organization->id,
            'responsible_user_id' => $admin->id,
            'visibility' => EventVisibility::Internal,
            'title' => 'NUR-INTERN',
        ]));

        $token = $this->feeds()->issue($this->organization);
        app()->forgetInstance('currentOrganization');

        $this->get(route('calendar.feed.organization', ['token' => $token]))
            ->assertOk()
            ->assertDontSee('NUR-INTERN');
    }

    public function test_unknown_short_and_revoked_tokens_yield_404(): void {
        $this->setUpOrganization();
        $token = $this->feeds()->issue($this->organization);
        app()->forgetInstance('currentOrganization');

        $this->get(route('calendar.feed.organization', ['token' => str_repeat('x', 48)]))->assertNotFound();
        $this->get(route('calendar.feed.organization', ['token' => 'kurz']))->assertNotFound();

        $this->feeds()->revoke($this->organization->fresh());
        $this->get(route('calendar.feed.organization', ['token' => $token]))->assertNotFound();
    }

    public function test_rotating_breaks_the_previous_subscription(): void {
        $this->setUpOrganization();
        $first = $this->feeds()->issue($this->organization);
        $second = $this->feeds()->issue($this->organization->fresh());
        app()->forgetInstance('currentOrganization');

        $this->assertNotSame($first, $second);
        $this->get(route('calendar.feed.organization', ['token' => $first]))->assertNotFound();
        $this->get(route('calendar.feed.organization', ['token' => $second]))->assertOk();
    }

    public function test_a_deactivated_organization_stops_serving_its_feed(): void {
        $this->setUpOrganization();
        $token = $this->feeds()->issue($this->organization);
        $this->organization->forceFill(['is_active' => false])->save();
        app()->forgetInstance('currentOrganization');

        $this->get(route('calendar.feed.organization', ['token' => $token]))->assertNotFound();
    }

    public function test_only_the_plain_token_is_stored_as_a_fingerprint(): void {
        $this->setUpOrganization();
        $token = $this->feeds()->issue($this->organization);
        $settings = (array) $this->organization->fresh()?->settings;

        $this->assertNotContains($token, $settings, 'Der Klartext-Token darf nicht in den Einstellungen stehen.');
        $this->assertSame(OrganizationCalendarFeedService::fingerprint($token), $settings[OrganizationCalendarFeedService::HASH_KEY] ?? null);
        $this->assertSame(mb_substr($token, 0, 6), $settings[OrganizationCalendarFeedService::HINT_KEY] ?? null);
    }

    public function test_managing_the_link_needs_the_organization_permission(): void {
        $this->setUpOrganization();

        $this->actingAs($this->orgUser())->get(route('events.feed.show'))->assertForbidden();
        $this->actingAs($this->orgUser())->post(route('events.feed.rotate'))->assertForbidden();

        $admin = $this->orgAdmin();
        $this->actingAs($admin)->get(route('events.feed.show'))->assertOk();

        // Der Klartext erscheint genau einmal — in der Umleitung nach dem Erzeugen.
        $rotate = $this->actingAs($admin)->post(route('events.feed.rotate'));
        $rotate->assertRedirect(route('events.feed.show'))->assertSessionHas('organization_calendar_feed_token');

        $token = (string) session('organization_calendar_feed_token');
        $this->actingAs($admin)->get(route('events.feed.show'))
            ->assertOk()
            ->assertSee(route('calendar.feed.organization', ['token' => $token]));

        // Beim nächsten Aufruf ist der Klartext weg, nur noch die Kennung bleibt.
        $follow = $this->actingAs($admin)->get(route('events.feed.show'));
        $follow->assertOk()->assertDontSee($token);
        $follow->assertSee(mb_substr($token, 0, 6));
    }
}
