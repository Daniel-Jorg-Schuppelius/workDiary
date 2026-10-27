<?php
/*
 * Created on   : Tue Oct 27 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : DomainExpiryScanTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Feature\Domain;

use App\Enums\Notification\NotificationEvent;
use App\Models\Domain\{DomainProjection, DomainProviderConnection};
use App\Models\Notification\NotificationDispatchLog;
use App\Services\Domain\DeadlineScans\DomainExpiryScan;
use App\Services\Notification\DeadlineScans\DeadlineScanOptions;
use App\Services\Notification\NotificationDispatcher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/**
 * Fristen-Scan der Domains (Feature 083): FAILUREDATE des Anbieters ist ein
 * Lebenszyklusdatum in der Zukunft, keine gescheiterte Verlängerung
 * (Befund 2026-10-27).
 */
class DomainExpiryScanTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
        $this->travelTo('2026-10-27 09:00:00');
    }

    /** @param  array<string, mixed>  $attributes */
    private function projection(DomainProviderConnection $connection, string $domain, array $attributes): DomainProjection {
        return DomainProjection::factory()->create(array_merge([
            'organization_id' => $this->organization->id, 'connection_id' => $connection->id, 'external_domain' => $domain,
            'domain_hash' => DomainProjection::hashFor($domain), 'status' => 'ACTIVE', 'renewal_mode' => 'AUTORENEW', 'synced_at' => now(),
        ], $attributes));
    }

    public function test_future_failure_date_is_the_normal_lifecycle_and_not_a_failed_renewal(): void {
        $admin = $this->orgAdmin();
        $connection = DomainProviderConnection::factory()->create(['organization_id' => $this->organization->id]);
        $healthy = $this->projection($connection, 'l0ves.de', ['expiration_at' => '2027-02-05', 'failure_at' => '2027-03-07', 'finalization_at' => '2027-03-21']);
        $failed = $this->projection($connection, 'cfl-world.de', ['expiration_at' => '2026-09-01', 'failure_at' => '2026-10-26', 'finalization_at' => '2026-11-10']);
        $expiring = $this->projection($connection, 'e-cld.de', ['expiration_at' => '2026-11-05', 'failure_at' => '2026-12-20']);

        $sent = app(DomainExpiryScan::class)->run(app(NotificationDispatcher::class), new DeadlineScanOptions(7, 30));

        $this->assertGreaterThan(0, $sent);
        $logged = NotificationDispatchLog::query()->where('event', NotificationEvent::DomainExpiring->value)->pluck('subject_id')->map('intval')->all();
        $this->assertEqualsCanonicalizing([$failed->id, $expiring->id], $logged, 'die gesunde Domain mit künftigem Failure-Datum wird nicht gemeldet');
        $this->assertNotContains($healthy->id, $logged);

        $titles = $admin->notifications()->get()->map(static fn($n): string => (string) ($n->data['title'] ?? ''))->all();
        $this->assertContains(__('notification.message.domain_renewal_failed_title', ['domain' => 'cfl-world.de']), $titles);
        $this->assertContains(__('notification.message.domain_expiring_title', ['domain' => 'e-cld.de']), $titles);
        $this->assertNotContains(__('notification.message.domain_expiring_title', ['domain' => 'l0ves.de']), $titles);
    }
}
