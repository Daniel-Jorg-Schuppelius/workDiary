<?php
/*
 * Created on   : Fri May 29 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : PublicTokenRouteThrottleTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Security;

use Illuminate\Support\Facades\Route;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Stellt sicher, dass die öffentlichen, tokenbasierten Routen (ohne Auth)
 * durch Rate-Limiting gegen Brute-Force/Enumeration und Abuse geschützt sind.
 */
class PublicTokenRouteThrottleTest extends TestCase {
    /**
     * @return array<string, array{0: string}>
     */
    public static function publicTokenRouteProvider(): array {
        return [
            'calendar feed' => ['calendar.feed.personal'],
            'timesheet sign show' => ['timesheets.public-sign'],
            'timesheet sign submit' => ['timesheets.public-sign.submit'],
            'protocol sign show' => ['protocols.public-sign'],
            'protocol sign submit' => ['protocols.public-sign.submit'],
            'backup heartbeat' => ['admin.backup.heartbeat'],
        ];
    }

    #[DataProvider('publicTokenRouteProvider')]
    public function test_public_token_route_has_throttle_middleware(string $routeName): void {
        $route = Route::getRoutes()->getByName($routeName);

        $this->assertNotNull($route, "Route '$routeName' nicht gefunden.");

        $hasThrottle = collect($route->gatherMiddleware())
            ->contains(fn(string $m): bool => str_starts_with($m, 'throttle'));

        $this->assertTrue(
            $hasThrottle,
            "Öffentliche Token-Route '$routeName' muss eine throttle-Middleware besitzen."
        );
    }
}
