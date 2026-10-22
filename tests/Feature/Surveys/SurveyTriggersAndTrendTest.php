<?php
/*
 * Created on   : Thu Oct 22 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : SurveyTriggersAndTrendTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Feature\Surveys;

use App\Enums\Diary\Status;
use App\Events\Diary\OrderCompleted;
use App\Mail\SurveyInvitationMail;
use App\Models\Customer\Customer;
use App\Models\Diary\DiaryEntry;
use App\Models\Platform\User;
use App\Models\Survey\{Survey, SurveyAnswer, SurveyInvitation};
use App\Services\Diary\OrderService;
use App\Services\Survey\SurveyService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\{Event, Mail};
use Spatie\Permission\PermissionRegistrar;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/** Umfrage-Auslöser Auftragsabschluss und fester Abstand (MVP-1293), NPS je Monat (MVP-1294). */
final class SurveyTriggersAndTrendTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private User $admin;

    private Customer $customer;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
        app(PermissionRegistrar::class)->setPermissionsTeamId($this->organization->id);
        $this->admin = User::factory()->admin()->create(['organization_id' => $this->organization->id]);
        $this->customer = Customer::factory()->create(['organization_id' => $this->organization->id, 'email' => 'kunde@example.test']);
    }

    protected function tearDown(): void {
        Carbon::setTestNow();
        parent::tearDown();
    }

    /** @param array<string, mixed> $attributes */
    private function survey(array $attributes = []): Survey {
        $survey = Survey::query()->create([
            'organization_id' => $this->organization->id, 'title' => 'Zufriedenheit', 'active' => true, 'anonymous' => false, 'created_by' => $this->admin->id,
            ...$attributes,
        ]);
        $survey->questions()->create(['organization_id' => $this->organization->id, 'type' => 'nps', 'label' => 'Empfehlung?', 'required' => true, 'position' => 1]);

        return $survey;
    }

    public function test_completing_an_order_invites_the_customer(): void {
        $this->survey(['trigger_on_order_completion' => true]);
        $this->survey(['title' => 'Ohne Auslöser']);
        $entry = DiaryEntry::factory()->create(['organization_id' => $this->organization->id, 'user_id' => $this->admin->id, 'customer_id' => $this->customer->id, 'status' => Status::InProgress]);

        Event::fakeFor(function () use ($entry): void {
            app(OrderService::class)->complete($entry, $this->admin, 'Erledigt');
            Event::assertDispatched(OrderCompleted::class, fn (OrderCompleted $event): bool => $event->diaryEntryId === $entry->id);
        }, [OrderCompleted::class]);

        Mail::fake();
        event(new OrderCompleted((int) $this->organization->id, (int) $entry->id));
        Mail::assertSent(SurveyInvitationMail::class, 1);
        $this->assertSame('order', SurveyInvitation::query()->sole()->context_kind);
    }

    public function test_scheduled_surveys_reach_active_customers_once_per_interval(): void {
        Mail::fake();
        $survey = $this->survey(['trigger_schedule_days' => 30]);
        $idle = Customer::factory()->create(['organization_id' => $this->organization->id, 'email' => 'ruhig@example.test']);
        DiaryEntry::factory()->create(['organization_id' => $this->organization->id, 'user_id' => $this->admin->id, 'customer_id' => $this->customer->id, 'completed_at' => now()->subDays(3)]);
        DiaryEntry::factory()->create(['organization_id' => $this->organization->id, 'user_id' => $this->admin->id, 'customer_id' => $idle->id, 'completed_at' => now()->subDays(90)]);

        $this->artisan('surveys:send-scheduled')->assertSuccessful();
        $this->artisan('surveys:send-scheduled')->assertSuccessful();

        $invitations = SurveyInvitation::query()->where('survey_id', $survey->id)->get();
        $this->assertCount(1, $invitations);
        $this->assertSame((int) $this->customer->id, (int) $invitations->first()->customer_id);
        $this->assertSame('schedule', $invitations->first()->context_kind);
    }

    public function test_nps_by_month_and_trigger_form(): void {
        Carbon::setTestNow('2026-10-15 12:00:00');
        $survey = $this->survey();
        $service = app(SurveyService::class);
        foreach ([['2026-09-05', 10], ['2026-09-06', 3], ['2026-10-02', 9]] as $i => [$date, $value]) {
            $issued = $service->invite($survey, "person{$i}@example.test");
            $response = $service->submit($issued['invitation'], [$survey->questions()->firstOrFail()->id => $value]);
            SurveyAnswer::query()->where('survey_response_id', $response->id)->update(['created_at' => $date . ' 10:00:00']);
        }

        $trend = collect($service->npsByMonth($survey, 3))->keyBy('month');
        $this->assertSame(['2026-08', '2026-09', '2026-10'], $trend->keys()->all());
        $this->assertNull($trend['2026-08']['score']);
        $this->assertSame(0, $trend['2026-09']['score']);
        $this->assertSame(2, $trend['2026-09']['count']);
        $this->assertSame(100, $trend['2026-10']['score']);

        $this->actingAs($this->admin)->post(route('surveys.triggers', $survey), [
            'trigger_on_order_completion' => '1', 'trigger_schedule_days' => 60,
        ])->assertSessionHas('success');
        $fresh = $survey->fresh();
        $this->assertTrue($fresh->trigger_on_order_completion);
        $this->assertFalse($fresh->trigger_on_ticket_close);
        $this->assertSame(60, $fresh->trigger_schedule_days);
        $this->actingAs($this->admin)->get(route('surveys.show', $survey))->assertOk()->assertSee(__('survey.trend.title'));
    }
}
