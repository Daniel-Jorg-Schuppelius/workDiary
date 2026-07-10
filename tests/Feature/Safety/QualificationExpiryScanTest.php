<?php
/*
 * Created on   : Sat Jun 14 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : QualificationExpiryScanTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Safety;

use App\Enums\Notification\{NotificationChannel, NotificationEvent};
use App\Models\Notification\{NotificationDispatchLog, NotificationRule};
use App\Models\{Qualification, User};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

class QualificationExpiryScanTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private User $employee;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();

        $this->employee = User::factory()->user()->create(['organization_id' => $this->organization->id]);

        NotificationRule::factory()->forEvent(NotificationEvent::QualificationExpiring)->create([
            'organization_id' => $this->organization->id,
            'channels' => [NotificationChannel::InApp->value],
        ]);
    }

    private function attach(Qualification $qualification, ?string $validUntil): void {
        $this->employee->qualifications()->attach($qualification->id, [
            'valid_from' => now()->subYear()->toDateString(),
            'valid_until' => $validUntil,
        ]);
    }

    public function test_expiring_qualification_notifies_employee_once(): void {
        $qualification = Qualification::factory()->create(['organization_id' => $this->organization->id]);
        $this->attach($qualification, now()->addDays(10)->toDateString());

        $this->artisan('notifications:scan-deadlines')->assertExitCode(0);
        $this->artisan('notifications:scan-deadlines')->assertExitCode(0);

        $this->assertSame(1, $this->employee->notifications()->count());
        $data = (array) $this->employee->notifications()->first()?->data;
        $this->assertSame(NotificationEvent::QualificationExpiring->value, $data['event'] ?? null);
        $this->assertSame(1, NotificationDispatchLog::query()->withoutGlobalScopes()
            ->where('event', NotificationEvent::QualificationExpiring->value)->count());
    }

    public function test_far_future_qualification_does_not_notify(): void {
        $qualification = Qualification::factory()->create(['organization_id' => $this->organization->id]);
        $this->attach($qualification, now()->addDays(120)->toDateString());

        $this->artisan('notifications:scan-deadlines')->assertExitCode(0);

        $this->assertSame(0, $this->employee->notifications()->count());
    }

    public function test_unlimited_qualification_does_not_notify(): void {
        $qualification = Qualification::factory()->create(['organization_id' => $this->organization->id]);
        $this->attach($qualification, null);

        $this->artisan('notifications:scan-deadlines')->assertExitCode(0);

        $this->assertSame(0, $this->employee->notifications()->count());
    }
}
