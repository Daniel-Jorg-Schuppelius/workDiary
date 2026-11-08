<?php
/*
 * Created on   : Sun Nov 08 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ProjectTargetsDemoBlockTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Feature\Project;

use App\Models\Platform\User;
use App\Models\Project\{Project, ProjectTimeShortfall};
use App\Services\Demo\Contracts\DemoSeedContext;
use App\Services\Project\Demo\ProjectTargetsDemoBlock;
use App\Support\Tz;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/** Demo der Projekt-Sollzeiten: der geseedete Fehlbestand passt zum Soll des Vortags. */
final class ProjectTargetsDemoBlockTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
    }

    protected function tearDown(): void {
        CarbonImmutable::setTestNow();
        parent::tearDown();
    }

    public function test_shortfall_follows_the_target_of_the_previous_day(): void {
        $admin = User::factory()->admin()->create(['organization_id' => $this->organization->id]);
        $project = Project::factory()->create(['organization_id' => $this->organization->id]);
        $block = new ProjectTargetsDemoBlock;

        // Samstag: Vortag Freitag mit der eigenen Zeile der ersten Person (240); Dienstag: Montag (480).
        foreach ([['2026-11-07 10:00', 240], ['2026-11-10 10:00', 480]] as [$now, $target]) {
            CarbonImmutable::setTestNow(CarbonImmutable::parse($now, Tz::ofOrganization($this->organization)));
            $block->purge($this->organization);
            $block->seed(new DemoSeedContext($this->organization, $admin, collect([$admin]), [], null, null, null, null, collect([$project])));

            $shortfall = ProjectTimeShortfall::query()->sole();
            $this->assertSame(
                [$target, $target - 90, 90],
                [(int) $shortfall->target_minutes, (int) $shortfall->booked_minutes, (int) $shortfall->shortfall_minutes],
            );
        }
    }
}
