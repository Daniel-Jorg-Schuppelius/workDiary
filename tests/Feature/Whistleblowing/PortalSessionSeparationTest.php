<?php
/*
 * Created on   : Thu Sep 17 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : PortalSessionSeparationTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Whistleblowing;

use App\Http\Middleware\UseAnonymousPortalSession;
use App\Models\Platform\User;
use App\Session\AnonymousStackSessionHandler;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Sicherheitsaudit 2026-09-17 (privacy-wb-1): Meldeportal, Betroffenenportal
 * und Karriereportal liefen in der Sitzung der angemeldeten Person — Fall und
 * Postfach-Geheimnis lagen in derselben Zeile wie deren `user_id`. Portal und
 * Anwendung führen daher getrennte Sitzungen, und die Portal-Zeile trägt keine
 * Kontobindung.
 */
final class PortalSessionSeparationTest extends TestCase {
    use RefreshDatabase;

    public function test_report_portal_uses_its_own_session_cookie(): void {
        $main = (string) config('session.cookie');

        $response = $this->get(route('whistleblowing.landing'));

        $response->assertOk();
        $this->assertNotNull($response->headers->getCookies()[0] ?? null);
        $names = array_map(static fn ($cookie): string => $cookie->getName(), $response->headers->getCookies());
        $this->assertContains($main . UseAnonymousPortalSession::SUFFIX, $names);
        $this->assertNotContains($main, $names, 'Die Portalsitzung darf die Anwendungssitzung nicht überschreiben.');
    }

    public function test_application_session_survives_a_portal_visit(): void {
        $user = User::factory()->user()->create(['is_new_system' => true]);

        $this->actingAs($user)->get(route('whistleblowing.landing'))->assertOk();

        $this->get(route('account.profile.edit'))->assertOk();
        $this->assertAuthenticatedAs($user);
    }

    public function test_portal_session_rows_carry_no_account_binding(): void {
        $user = User::factory()->user()->create(['is_new_system' => true]);
        $this->actingAs($user)->get(route('whistleblowing.landing'))->assertOk();

        $handler = new AnonymousStackSessionHandler(
            app('db')->connection(config('session.connection')),
            (string) config('session.table', 'sessions'),
            (int) config('session.lifetime', 120),
            app(),
        );

        $payload = ['user_id' => $user->id];
        $addUserInformation = \Closure::bind(
            function (array &$row) { $this->addUserInformation($row); },
            $handler,
            AnonymousStackSessionHandler::class,
        );
        $addUserInformation($payload);

        $this->assertNull($payload['user_id'], 'Die Sitzungszeile des Portals darf kein Konto benennen.');
    }
}
