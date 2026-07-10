<?php
/*
 * Created on   : Sat Jun 14 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : AssetReturnOverdueScanTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Asset;

use App\Enums\Notification\{NotificationChannel, NotificationEvent};
use App\Models\{Asset, AssetAssignment, User};
use App\Models\Notification\{NotificationDispatchLog, NotificationRule};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

class AssetReturnOverdueScanTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private User $borrower;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();

        $this->borrower = User::factory()->user()->create(['organization_id' => $this->organization->id]);

        NotificationRule::factory()->forEvent(NotificationEvent::AssetReturnOverdue)->create([
            'organization_id' => $this->organization->id,
            'channels' => [NotificationChannel::InApp->value],
        ]);
    }

    public function test_overdue_return_notifies_borrower_exactly_once(): void {
        $asset = Asset::factory()->create(['organization_id' => $this->organization->id]);
        AssetAssignment::factory()->create([
            'organization_id' => $this->organization->id,
            'asset_id' => $asset->id,
            'assigned_to_user_id' => $this->borrower->id,
            'expected_return_at' => now()->subDays(2),
            'returned_at' => null,
        ]);

        $this->artisan('notifications:scan-deadlines')->assertExitCode(0);
        $this->artisan('notifications:scan-deadlines')->assertExitCode(0);

        $this->assertSame(1, $this->borrower->notifications()->count());
        $data = (array) $this->borrower->notifications()->first()?->data;
        $this->assertSame(NotificationEvent::AssetReturnOverdue->value, $data['event'] ?? null);

        $this->assertSame(1, NotificationDispatchLog::query()->withoutGlobalScopes()->count());
    }

    public function test_returned_assignment_does_not_notify(): void {
        $asset = Asset::factory()->create(['organization_id' => $this->organization->id]);
        AssetAssignment::factory()->create([
            'organization_id' => $this->organization->id,
            'asset_id' => $asset->id,
            'assigned_to_user_id' => $this->borrower->id,
            'expected_return_at' => now()->subDays(2),
            'returned_at' => now()->subDay(),
        ]);

        $this->artisan('notifications:scan-deadlines')->assertExitCode(0);

        $this->assertSame(0, $this->borrower->notifications()->count());
    }
}
