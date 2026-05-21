<?php
/*
 * Created on   : Thu May 21 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : CertificateIssueTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Events;

use App\Enums\Event\ParticipantRole;
use App\Enums\Event\ParticipantStatus;
use App\Models\Event;
use App\Models\EventCategory;
use App\Models\User;
use App\Services\Event\CertificateService;
use Database\Seeders\PermissionsSeeder;
use Database\Seeders\RolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

class CertificateIssueTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private User $user;

    private CertificateService $svc;

    protected function setUp(): void {
        parent::setUp();
        $this->seed(RolesSeeder::class);
        $this->seed(PermissionsSeeder::class);
        $this->setUpOrganization();
        $this->user = User::factory()->create(['organization_id' => $this->organization->id]);
        $this->svc = app(CertificateService::class);
    }

    public function test_issue_uses_event_certificate_valid_months(): void {
        $event = $this->makeEvent(['certificate_valid_months' => 24]);
        $this->attachUser($event);

        $issuedAt = now();
        $pivot = $this->svc->issue($event, $this->user, $issuedAt);

        $this->assertNotNull($pivot->certificate_issued_at);
        $this->assertNotNull($pivot->certificate_expires_at);
        $this->assertSame(
            $issuedAt->copy()->startOfDay()->addMonths(24)->toDateString(),
            $pivot->certificate_expires_at->toDateString(),
        );
        $this->assertSame(ParticipantStatus::Attended, $pivot->status);
    }

    public function test_issue_falls_back_to_category_valid_months(): void {
        $category = EventCategory::factory()->withCertificate(6)->create([
            'organization_id' => $this->organization->id,
        ]);
        $event = $this->makeEvent([
            'certificate_valid_months' => null,
            'category_id' => $category->id,
        ]);
        $this->attachUser($event);

        $pivot = $this->svc->issue($event, $this->user);

        $this->assertSame(
            now()->startOfDay()->addMonths(6)->toDateString(),
            $pivot->certificate_expires_at->toDateString(),
        );
    }

    public function test_issue_falls_back_to_config_default(): void {
        config()->set('events.certificate.default_valid_months', 12);
        $event = $this->makeEvent(['certificate_valid_months' => null]);
        $this->attachUser($event);

        $pivot = $this->svc->issue($event, $this->user);

        $this->assertSame(
            now()->startOfDay()->addMonths(12)->toDateString(),
            $pivot->certificate_expires_at->toDateString(),
        );
    }

    /** @param array<string, mixed> $overrides */
    private function makeEvent(array $overrides = []): Event {
        return Event::factory()->create(array_merge([
            'organization_id' => $this->organization->id,
            'responsible_user_id' => $this->user->id,
            'started_at' => now()->subDay(),
            'ended_at' => now()->subDay()->addHours(2),
        ], $overrides));
    }

    private function attachUser(Event $event): void {
        $event->participants()->syncWithoutDetaching([
            $this->user->id => [
                'role' => ParticipantRole::Attendee->value,
                'status' => ParticipantStatus::Invited->value,
            ],
        ]);
    }
}
