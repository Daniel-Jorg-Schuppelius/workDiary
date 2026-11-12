<?php
/*
 * Created on   : Tue Nov 10 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ActivityCategoryWorkTimeTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Flextime;

use App\Enums\TimeEntry\{TimeEntryActivityType, TimeEntryKind};
use App\Models\Classification\ActivityCategory;
use App\Models\Platform\User;
use App\Models\Time\TimeEntry;
use App\Services\Flextime\FlexCalculator;
use App\Services\Reporting\WorkBalanceCalculator;
use App\Services\TimeApproval\DayCloseService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/** Tätigkeitskategorie (MVP-1554): „Zählt als Arbeit“ und „Standardmäßig abrechenbar“ wirken. */
class ActivityCategoryWorkTimeTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private const string DAY = '2030-01-15';

    private User $user;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization(['timezone' => 'UTC']);
        $this->user = $this->orgUser();
    }

    public function test_category_without_work_flag_stays_out_of_flex_day_close_and_balance(): void {
        $this->entry(60, null);
        $this->entry(45, $this->category(['counts_as_work' => true]));
        $excluded = $this->entry(30, $this->category(['counts_as_work' => false]));
        $day = CarbonImmutable::parse(self::DAY);

        $this->assertFalse($excluded->fresh()->countsAsWork());
        $this->assertSame(105, app(FlexCalculator::class)->actualMinutes($this->user, $day));
        $this->assertSame(105, app(WorkBalanceCalculator::class)->daily($this->user, $day)->trackedMinutes);
        $this->assertSame(105, app(DayCloseService::class)->context($this->user, $day)['aggregates']['booked']);
        $this->assertSame(105, (int) TimeEntry::query()->where('user_id', $this->user->id)->countedAsWork()->sum('minutes'));
    }

    public function test_category_presets_billability_of_new_administrative_time(): void {
        $billable = $this->category(['billable_default' => true]);
        $internal = $this->category(['billable_default' => false]);

        foreach ([[$billable, true], [$internal, false], [null, true]] as [$category, $expected]) {
            $this->actingAs($this->user)->post(route('admin-time-entries.store'), array_filter([
                'date' => self::DAY,
                'minutes' => 30,
                'activity_type' => TimeEntryActivityType::Admin->value,
                'activity_category_id' => $category?->sqid,
            ]))->assertSessionHasNoErrors();

            $entry = TimeEntry::query()->latest('id')->firstOrFail();
            $this->assertSame($category?->id, $entry->activity_category_id);
            $this->assertSame($expected, (bool) $entry->billable);
        }
    }

    public function test_admin_dialog_marks_the_billable_default_of_each_category(): void {
        $this->category(['billable_default' => true, 'label' => 'Kundentermin']);

        $this->actingAs($this->user)->get(route('admin-time-entries.create') . '?dialog=1')->assertOk()
            ->assertSee('data-billable-default="1"', false);
    }

    /** @param  array<string, mixed>  $attributes */
    private function category(array $attributes): ActivityCategory {
        return ActivityCategory::factory()->create(['organization_id' => $this->organization->id] + $attributes);
    }

    private function entry(int $minutes, ?ActivityCategory $category): TimeEntry {
        return TimeEntry::create([
            'organization_id' => $this->organization->id,
            'user_id' => $this->user->id,
            'date' => self::DAY,
            'minutes' => $minutes,
            'kind' => TimeEntryKind::Work->value,
            'activity_type' => TimeEntryActivityType::Admin->value,
            'activity_category_id' => $category?->id,
        ]);
    }
}
