<?php
/*
 * Created on   : Fri May 22 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : PublicRouteTenantTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Tenant;

use App\Enums\Timesheet\{TimesheetKind, TimesheetStatus};
use App\Models\{Organization, Timesheet, User};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Public-Routes (ohne Auth-Middleware) müssen ihre Mandantengrenze
 * über das mitgereichte Token herstellen. Diese Suite belegt:
 *   - PublicSignature (sign/timesheet/{token}) lädt das Timesheet
 *     korrekt und 404t bei unbekannten Tokens.
 *   - Magic-Token darf nicht an fremde Org-Daten leaken.
 *   - Personal-Schedule-ICS-Feed (calendar/feed/{token}.ics) liefert
 *     ausschließlich dem Tokeninhaber zugehörige Daten.
 *
 * Referenz: ../WorkDiary-Architecture/security/tenant-audit-2026.md, Abschnitt „Public-Routes".
 */
class PublicRouteTenantTest extends TestCase {
    use RefreshDatabase;

    private Organization $orgA;

    private Organization $orgB;

    private User $userA;

    private User $userB;

    protected function setUp(): void {
        parent::setUp();

        $this->orgA = Organization::factory()->create(['slug' => 'pub-a']);
        $this->orgB = Organization::factory()->create(['slug' => 'pub-b']);

        $this->userA = User::factory()->user()->create(['organization_id' => $this->orgA->id]);
        $this->userB = User::factory()->user()->create(['organization_id' => $this->orgB->id]);
    }

    public function test_public_signature_with_valid_token_finds_timesheet(): void {
        $token = Str::random(48);
        $timesheetB = $this->withOrg($this->orgB, fn() => Timesheet::create([
            'user_id' => $this->userB->id,
            'work_date' => now()->toDateString(),
            'kind' => TimesheetKind::Project,
            'status' => TimesheetStatus::Submitted,
            'magic_token_hash' => \App\Models\Timesheet::hashMagicToken($token),
            'magic_expires_at' => now()->addHour(),
        ]));

        // Ohne gebundene currentOrganization (Public-Route!) auf die signierte
        // Show-Route gehen. Token muss aufgelöst werden, Response 200.
        app()->forgetInstance('currentOrganization');
        $response = $this->get(route('timesheets.public-sign', ['token' => $token]));
        $this->assertSame(200, $response->status());

        // Hardening: Controller bindet currentOrganization an die Org des
        // Timesheets. Damit sehen nachgelagerte Queries genau diese Org.
        $current = app('currentOrganization');
        $this->assertInstanceOf(Organization::class, $current);
        $this->assertSame((int) $timesheetB->organization_id, (int) $current->id);
    }

    public function test_public_signature_with_unknown_token_is_404(): void {
        app()->forgetInstance('currentOrganization');

        $response = $this->get(route('timesheets.public-sign', ['token' => Str::random(48)]));
        $this->assertSame(404, $response->status());
    }

    public function test_public_signature_with_expired_token_is_410(): void {
        $token = Str::random(48);
        $this->withOrg($this->orgB, fn() => Timesheet::create([
            'user_id' => $this->userB->id,
            'work_date' => now()->toDateString(),
            'kind' => TimesheetKind::Project,
            'status' => TimesheetStatus::Submitted,
            'magic_token_hash' => \App\Models\Timesheet::hashMagicToken($token),
            'magic_expires_at' => now()->subMinute(),
        ]));

        app()->forgetInstance('currentOrganization');
        $response = $this->get(route('timesheets.public-sign', ['token' => $token]));
        $this->assertSame(410, $response->status());
    }

    public function test_public_signature_token_cannot_be_guessed_by_other_org_user(): void {
        // Token wird gar nicht geleaked, aber wir simulieren einen Angreifer
        // aus Org A, der einen zufälligen Token rät – muss 404 sein.
        $this->withOrg($this->orgB, fn() => Timesheet::create([
            'user_id' => $this->userB->id,
            'work_date' => now()->toDateString(),
            'kind' => TimesheetKind::Project,
            'status' => TimesheetStatus::Submitted,
            'magic_token_hash' => \App\Models\Timesheet::hashMagicToken(Str::random(48)),
            'magic_expires_at' => now()->addHour(),
        ]));

        app()->instance('currentOrganization', $this->orgA);
        $this->actingAs($this->userA);

        $response = $this->get(route('timesheets.public-sign', ['token' => Str::random(48)]));
        $this->assertSame(404, $response->status());
    }

    public function test_personal_schedule_token_with_short_token_is_404(): void {
        app()->forgetInstance('currentOrganization');

        $response = $this->get('/calendar/feed/' . Str::random(8) . '.ics');
        $this->assertSame(404, $response->status());
    }

    public function test_personal_schedule_token_unknown_long_token_is_404(): void {
        app()->forgetInstance('currentOrganization');

        $response = $this->get('/calendar/feed/' . Str::random(48) . '.ics');
        $this->assertSame(404, $response->status());
    }

    public function test_personal_schedule_token_resolves_only_owning_user(): void {
        $tokenB = Str::random(48);
        $this->userB->forceFill(['calendar_feed_token_hash' => \App\Models\User::hashCalendarFeedToken($tokenB)])->save();

        app()->forgetInstance('currentOrganization');
        $response = $this->get('/calendar/feed/' . $tokenB . '.ics');
        $this->assertSame(200, $response->status());
        $this->assertStringContainsString('BEGIN:VCALENDAR', (string) $response->getContent());
    }

    /**
     * Bis 2026-09-13 lief dieser Feed ohne Anmeldung und über ALLE Mandanten:
     * Titel, Ort und Räume jedes als „öffentlich" markierten Termins lagen im
     * offenen Netz. Er hing an keiner Oberfläche, und `Visibility=Public` wirkt
     * sonst nirgends mandantenübergreifend. Jetzt: angemeldet und auf die
     * eigene Organisation begrenzt (Mandanten-Review).
     */
    public function test_public_ics_requires_auth_and_stays_inside_the_own_tenant(): void {
        $orgBPublic = $this->withOrg($this->orgB, fn () => \App\Models\Event::factory()->create([
            'organization_id' => $this->orgB->id,
            'responsible_user_id' => $this->userB->id,
            'visibility' => \App\Enums\Event\EventVisibility::Public,
            'title' => 'GEHEIM-ORG-B-PUBLICEVENT',
        ]));
        $orgAPublic = $this->withOrg($this->orgA, fn () => \App\Models\Event::factory()->create([
            'organization_id' => $this->orgA->id,
            'responsible_user_id' => $this->userA->id,
            'visibility' => \App\Enums\Event\EventVisibility::Public,
            'title' => 'ORG-A-OEFFENTLICH',
        ]));

        app()->forgetInstance('currentOrganization');
        $anonymous = $this->get(route('events.ics.public'));
        $this->assertContains($anonymous->status(), [302, 401], 'Feed ohne Anmeldung muss 302/401 liefern, war: ' . $anonymous->status());
        $this->assertStringNotContainsString('GEHEIM-ORG-B-PUBLICEVENT', (string) $anonymous->getContent());

        app()->forgetInstance('currentOrganization');
        $response = $this->actingAs($this->userA)->get(route('events.ics.public'));
        $response->assertOk();
        $body = (string) $response->getContent();
        $this->assertStringContainsString('ORG-A-OEFFENTLICH', $body);
        $this->assertStringNotContainsString('GEHEIM-ORG-B-PUBLICEVENT', $body);
    }

    public function test_personal_ics_feed_requires_auth_and_scopes_to_own_user(): void {
        // Unauth → Redirect/401 (web-Auth-Middleware).
        $response = $this->get(route('events.ics.personal'));
        $this->assertContains($response->status(), [302, 401], 'Personal-ICS ohne Auth muss 302/401 liefern, war: ' . $response->status());

        // Mit Org-A-User eingeloggt → 200, kein Org-B-Event im Body.
        $orgBEvent = $this->withOrg($this->orgB, fn() => \App\Models\Event::factory()->create([
            'organization_id' => $this->orgB->id,
            'responsible_user_id' => $this->userB->id,
            'title' => 'GEHEIM-ORG-B-EVENTPERSONAL',
        ]));

        $this->actingAs($this->userA);
        $response = $this->get(route('events.ics.personal'));
        $response->assertOk();
        $this->assertStringNotContainsString('GEHEIM-ORG-B-EVENTPERSONAL', (string) $response->getContent());
    }

    /**
     * @template T
     * @param  \Closure(): T  $callback
     * @return T
     */
    private function withOrg(Organization $org, \Closure $callback): mixed {
        $previous = app()->bound('currentOrganization') ? app('currentOrganization') : null;
        app()->instance('currentOrganization', $org);
        try {
            return $callback();
        } finally {
            if ($previous instanceof Organization) {
                app()->instance('currentOrganization', $previous);
            } else {
                app()->forgetInstance('currentOrganization');
            }
        }
    }
}
