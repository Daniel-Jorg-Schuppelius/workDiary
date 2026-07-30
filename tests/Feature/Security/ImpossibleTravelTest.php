<?php
/*
 * Created on   : Thu Jul 30 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ImpossibleTravelTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Feature\Security;

use App\Models\{SecurityEvent, User, UserKnownDevice};
use App\Services\Security\{ImpossibleTravelDetector, SecurityEventLogger};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/**
 * MVP-449 — Impossible-Travel-Erkennung: Geschwindigkeits-/Distanzschwellen,
 * stille Degradation ohne `.mmdb`, keine Auto-Abmeldung, Alarm an Nutzer und
 * Plattform-Admins.
 */
class ImpossibleTravelTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private User $user;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
        $this->user = $this->orgUser();
        Notification::fake();
    }

    /**
     * Geo-Auflösung faken (kein .mmdb im Test): überschreibt die protected
     * Test-Nähte des Detectors, da der statische Toolkit-Helper
     * (IpLocationHelper) nicht container-fakebar ist.
     *
     * @param array{lat: float, lon: float}|null $coordinates
     */
    private function fakeGeo(?array $coordinates, bool $available = true): void {
        $this->swap(ImpossibleTravelDetector::class, new class($coordinates, $available, app(SecurityEventLogger::class)) extends ImpossibleTravelDetector {
            /** @param array{lat: float, lon: float}|null $fakeCoordinates */
            public function __construct(
                private readonly ?array $fakeCoordinates,
                private readonly bool $isAvailable,
                SecurityEventLogger $security,
            ) {
                parent::__construct($security);
            }

            protected function geoAvailable(): bool {
                return $this->isAvailable;
            }

            protected function coordinates(?string $ip): ?array {
                return $this->fakeCoordinates;
            }

            protected function label(?string $ip): ?string {
                return 'Teststadt, Testland';
            }
        });
    }

    /** Referenzposition (Berlin) mit gegebenem Alter. */
    private function lastKnownPosition(int $minutesAgo): UserKnownDevice {
        return UserKnownDevice::query()->create([
            'user_id' => $this->user->id,
            'fingerprint' => 'fp-' . $minutesAgo,
            'label' => 'Firefox / Linux',
            'country' => 'Deutschland',
            'latitude' => 52.52,
            'longitude' => 13.405,
            'last_seen_at' => now()->subMinutes($minutesAgo),
        ]);
    }

    private function detector(): ImpossibleTravelDetector {
        return app(ImpossibleTravelDetector::class);
    }

    public function test_detects_physically_impossible_speed(): void {
        $this->lastKnownPosition(30);
        // Tokio: ~8 900 km von Berlin — in 30 Minuten unmöglich.
        $this->fakeGeo(['lat' => 35.68, 'lon' => 139.69]);

        $result = $this->detector()->check($this->user, '203.0.113.10');

        $this->assertNotNull($result);
        $this->assertGreaterThan(900, $result['speed_kmh']);
        $this->assertDatabaseHas('security_events', ['event' => 'auth.impossible_travel']);
        Notification::assertSentTo($this->user, \App\Notifications\GenericEventNotification::class);
    }

    public function test_plausible_travel_is_not_flagged(): void {
        $this->lastKnownPosition(600); // 10 Stunden
        // München: ~504 km von Berlin → ~50 km/h, völlig plausibel.
        $this->fakeGeo(['lat' => 48.14, 'lon' => 11.58]);

        $this->assertNull($this->detector()->check($this->user, '203.0.113.11'));
        $this->assertSame(0, SecurityEvent::query()->where('event', 'auth.impossible_travel')->count());
    }

    public function test_short_distance_is_ignored_even_when_fast(): void {
        $this->lastKnownPosition(1);
        // Potsdam: ~27 km — unter der Mindestdistanz, trotz hoher Rechenrate.
        $this->fakeGeo(['lat' => 52.39, 'lon' => 13.06]);

        $this->assertNull($this->detector()->check($this->user, '203.0.113.12'));
    }

    public function test_degrades_silently_without_geo_database(): void {
        $this->lastKnownPosition(5);
        $this->fakeGeo(['lat' => 35.68, 'lon' => 139.69], available: false);

        $this->assertNull($this->detector()->check($this->user, '203.0.113.13'));
        $this->assertSame(0, SecurityEvent::query()->count());
    }

    public function test_first_geo_login_has_no_reference_position(): void {
        $this->fakeGeo(['lat' => 35.68, 'lon' => 139.69]);

        $this->assertNull($this->detector()->check($this->user, '203.0.113.14'));
    }

    public function test_disabled_by_configuration(): void {
        config()->set('security.impossible_travel.enabled', false);
        $this->lastKnownPosition(5);
        $this->fakeGeo(['lat' => 35.68, 'lon' => 139.69]);

        $this->assertNull($this->detector()->check($this->user, '203.0.113.15'));
    }
}
