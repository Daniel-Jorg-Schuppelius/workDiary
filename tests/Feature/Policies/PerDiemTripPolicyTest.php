<?php
/*
 * Created on   : Sun Jul 13 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : PerDiemTripPolicyTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Feature\Policies;

use App\Enums\Expense\PerDiemTripStatus;
use App\Models\{PerDiemTrip, User};
use App\Policies\PerDiemTripPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\{BuildsPolicyActors, WithOrganization};
use Tests\TestCase;

/**
 * Reisen (Verpflegungsmehraufwand): strikt eigentümergebunden; ändern/löschen/
 * umwandeln nur im Draft (Converted ist final, Storno bis auf Cancelled).
 */
final class PerDiemTripPolicyTest extends TestCase {
    use BuildsPolicyActors;
    use RefreshDatabase;
    use WithOrganization;

    private PerDiemTripPolicy $policy;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
        $this->actAsTeam($this->organization);
        $this->policy = new PerDiemTripPolicy;
    }

    private function trip(User $owner, PerDiemTripStatus $status): PerDiemTrip {
        $trip = new PerDiemTrip;
        $trip->user_id = $owner->id;
        $trip->status = $status;

        return $trip;
    }

    public function test_owner_manages_draft_trip(): void {
        $owner = $this->actorIn($this->organization);
        $draft = $this->trip($owner, PerDiemTripStatus::Draft);

        $this->assertTrue($this->policy->view($owner, $draft));
        $this->assertTrue($this->policy->update($owner, $draft));
        $this->assertTrue($this->policy->delete($owner, $draft));
        $this->assertTrue($this->policy->convert($owner, $draft));
        $this->assertTrue($this->policy->cancel($owner, $draft));
    }

    public function test_converted_trip_is_immutable_except_cancel(): void {
        $owner = $this->actorIn($this->organization);
        $converted = $this->trip($owner, PerDiemTripStatus::Converted);
        $cancelled = $this->trip($owner, PerDiemTripStatus::Cancelled);

        $this->assertFalse($this->policy->update($owner, $converted));
        $this->assertFalse($this->policy->delete($owner, $converted));
        $this->assertFalse($this->policy->convert($owner, $converted));
        $this->assertTrue($this->policy->cancel($owner, $converted));
        $this->assertFalse($this->policy->cancel($owner, $cancelled), 'Stornierte Reise ist final.');
    }

    public function test_non_owner_is_denied(): void {
        $owner = $this->actorIn($this->organization);
        $other = $this->actorIn($this->organization);
        $draft = $this->trip($owner, PerDiemTripStatus::Draft);

        $this->assertFalse($this->policy->view($other, $draft));
        $this->assertFalse($this->policy->update($other, $draft));
        $this->assertFalse($this->policy->convert($other, $draft));
        $this->assertFalse($this->policy->cancel($other, $draft));
    }
}
