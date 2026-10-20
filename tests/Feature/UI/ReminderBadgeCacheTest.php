<?php
/*
 * Created on   : Tue Oct 20 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ReminderBadgeCacheTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Feature\UI;

use App\Enums\Expense\ExpenseStatus;
use App\Models\Platform\User;
use App\Models\Travel\{Expense, ExpenseCategory};
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/**
 * Die Erinnerungen im Kopf des Layouts liegen eine Minute je Nutzer im Cache,
 * als Arrays: Objekte liest der Cache nicht zurück (Befund 2026-10-20), der
 * zweite Seitenaufruf rendert aus dem Cache.
 */
final class ReminderBadgeCacheTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    public function test_reminders_render_from_the_cache_on_the_second_page_load(): void {
        $this->setUpOrganization();
        $user = User::factory()->user()->create(['organization_id' => $this->organization->id]);
        Expense::factory()->create([
            'organization_id' => $this->organization->id,
            'user_id' => $user->id,
            'expense_category_id' => ExpenseCategory::factory()->create(['organization_id' => $this->organization->id])->id,
            'status' => ExpenseStatus::Draft,
            'created_at' => CarbonImmutable::now()->subDays(10),
        ]);

        $this->actingAs($user)->get(route('problem-reports.index'))->assertOk();

        $items = Cache::get('reminders:2:' . $user->id . ':' . app()->getLocale());
        $this->assertIsArray($items);
        $this->assertSame('expense_drafts', $items[0]['key'] ?? null);
        $this->assertIsString($items[0]['title']);

        $this->actingAs($user)->get(route('problem-reports.index'))->assertOk()->assertSee($items[0]['title']);
    }
}
