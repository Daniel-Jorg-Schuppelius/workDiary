<?php
/*
 * Created on   : Fri May 22 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : PerDiemTripControllerTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Expense;

use App\Enums\Expense\{ExpenseStatus, PerDiemTripStatus};
use App\Models\{ExpenseCategory, PerDiemTrip};
use App\Models\Platform\User;
use Database\Seeders\PerDiemRateSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

class PerDiemTripControllerTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private User $user;

    protected function setUp(): void {
        parent::setUp();
        $this->seed(PerDiemRateSeeder::class);
        $this->setUpOrganization();
        $this->user = User::factory()->user()->create(['organization_id' => $this->organization->id]);
        // Die Verpflegungs-Kategorie kommt seit J3 (Vollscan 2026-08-23) mit der
        // Org-Erstausstattung über den OrganizationObserver.
        $this->assertTrue(ExpenseCategory::query()->where('slug', ExpenseCategory::SLUG_MEALS)->exists());
    }

    public function test_index_renders(): void {
        $this->getAsUser('per-diem-trips.index')->assertOk();
    }

    public function test_store_creates_trip_with_days(): void {
        $response = $this->postAsUser('per-diem-trips.store', [
            'country' => 'DE',
            'location' => 'Frankfurt',
            'purpose' => 'Workshop',
            'started_at' => '2025-03-10T08:00',
            'ended_at' => '2025-03-12T18:00',
            'accommodation_provided' => 0,
            'notes' => '',
        ]);

        $trip = PerDiemTrip::query()->where('user_id', $this->user->id)->firstOrFail();
        $response->assertRedirect(route('per-diem-trips.show', $trip));
        $this->assertSame(3, $trip->days()->count());
        $this->assertEqualsWithDelta(56.0, (float) $trip->totalAmount(), 0.01);
    }

    /** UI-Fuzz 2026-09-21: das Ende stand im Formular in UTC — jedes Speichern zog es um den Zeitzonenversatz vor. */
    public function test_edit_form_shows_start_and_end_in_local_time(): void {
        $this->postAsUser('per-diem-trips.store', [
            'country' => 'DE',
            'location' => 'Frankfurt',
            'purpose' => 'Workshop',
            'started_at' => '2025-07-10T08:00',
            'ended_at' => '2025-07-11T18:00',
            'accommodation_provided' => 0,
        ]);
        $trip = PerDiemTrip::query()->where('user_id', $this->user->id)->firstOrFail();

        $this->actingAs($this->user)->get(route('per-diem-trips.edit', $trip))
            ->assertOk()
            ->assertSee('2025-07-10T08:00')
            ->assertSee('2025-07-11T18:00');
    }

    /** UI-Fuzz 2026-09-21: eine Reise vor dem ältesten Verpflegungssatz endete in 500. */
    public function test_trip_without_rate_is_a_field_error(): void {
        $this->postAsUser('per-diem-trips.store', [
            'country' => 'DE',
            'location' => 'Frankfurt',
            'purpose' => 'Nachtrag',
            'started_at' => '1990-05-10T08:00',
            'ended_at' => '1990-05-10T18:00',
            'accommodation_provided' => 0,
        ])->assertSessionHasErrors('started_at');

        $this->assertSame(0, PerDiemTrip::query()->where('user_id', $this->user->id)->count());
    }

    /** UI-Fuzz 2026-09-21: der Arbeitsort-Schlüssel (varchar(100)) wird aus dem Ort (255) abgeleitet — langer Ort endete in 1406. */
    public function test_long_location_shortens_the_workplace_key(): void {
        $this->postAsUser('per-diem-trips.store', [
            'country' => 'DE',
            'location' => str_repeat('Frankfurt am Main ', 12),
            'purpose' => 'Messe',
            'started_at' => '2025-03-10T08:00',
            'ended_at' => '2025-03-10T18:00',
            'accommodation_provided' => 0,
        ])->assertSessionHasNoErrors();

        $trip = PerDiemTrip::query()->where('user_id', $this->user->id)->firstOrFail();
        $this->assertSame(100, mb_strlen((string) $trip->workplace_key));
    }

    public function test_convert_creates_expense_and_sets_status(): void {
        $this->postAsUser('per-diem-trips.store', [
            'country' => 'DE',
            'location' => 'Berlin',
            'purpose' => 'Konferenz',
            'started_at' => '2025-03-10T08:00',
            'ended_at' => '2025-03-11T18:00',
            'accommodation_provided' => 0,
        ]);
        $trip = PerDiemTrip::query()->where('user_id', $this->user->id)->firstOrFail();

        $this->postAsUser('per-diem-trips.convert', [], $trip)->assertRedirect(route('expenses.index'));
        $trip->refresh();
        $this->assertSame(PerDiemTripStatus::Converted, $trip->status);
        $this->assertNotNull($trip->expense_id);
        $this->assertSame(ExpenseStatus::Pending->value, $trip->expense->status->value);
    }

    public function test_destroy_only_for_draft(): void {
        $this->postAsUser('per-diem-trips.store', [
            'country' => 'DE',
            'location' => 'München',
            'purpose' => 'Meeting',
            'started_at' => '2025-03-10T08:00',
            'ended_at' => '2025-03-11T18:00',
        ]);
        $trip = PerDiemTrip::query()->where('user_id', $this->user->id)->firstOrFail();
        $this->deleteAsUser('per-diem-trips.destroy', $trip)->assertRedirect();
        $this->assertSame(0, PerDiemTrip::query()->count());
    }

    public function test_pdf_download_returns_attachment(): void {
        $this->postAsUser('per-diem-trips.store', [
            'country' => 'DE',
            'location' => 'Hamburg',
            'purpose' => 'Audit',
            'started_at' => '2025-03-10T08:00',
            'ended_at' => '2025-03-12T18:00',
            'accommodation_provided' => 0,
        ]);
        $trip = PerDiemTrip::query()->where('user_id', $this->user->id)->firstOrFail();

        $response = $this->getAsUser('per-diem-trips.pdf', $trip);

        $response->assertOk();
        $response->assertHeader('content-type', 'application/pdf');
        $disposition = $response->headers->get('content-disposition');
        $this->assertNotNull($disposition);
        $this->assertStringContainsString('attachment', $disposition);
        $this->assertStringContainsString('verpflegungspauschale-2025-03-10-hamburg', $disposition);
        $this->assertStringStartsWith('%PDF', $response->getContent() ?: '');
    }

    public function test_pdf_forbidden_for_other_user(): void {
        $other = User::factory()->user()->create(['organization_id' => $this->organization->id]);
        $this->postAsUser('per-diem-trips.store', [
            'country' => 'DE',
            'location' => 'Köln',
            'purpose' => 'Schulung',
            'started_at' => '2025-03-10T08:00',
            'ended_at' => '2025-03-11T18:00',
        ]);
        $trip = PerDiemTrip::query()->where('user_id', $this->user->id)->firstOrFail();

        $this->actingAs($other);
        $this->get(route('per-diem-trips.pdf', $trip))->assertForbidden();
    }

    private function getAsUser(string $routeName, mixed $parameters = []): TestResponse {
        return $this->actingAs($this->user)->get(route($routeName, $parameters));
    }

    private function postAsUser(string $routeName, array $payload = [], mixed $parameters = []): TestResponse {
        return $this->actingAs($this->user)->post(route($routeName, $parameters), $payload);
    }

    private function deleteAsUser(string $routeName, mixed $parameters = []): TestResponse {
        return $this->actingAs($this->user)->delete(route($routeName, $parameters));
    }
}
