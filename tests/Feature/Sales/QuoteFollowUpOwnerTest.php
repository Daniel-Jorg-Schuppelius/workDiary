<?php
/*
 * Created on   : Wed Nov 11 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : QuoteFollowUpOwnerTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Feature\Sales;

use App\Enums\Sales\QuoteStatus;
use App\Models\Customer\Customer;
use App\Models\Platform\{Organization, User};
use App\Models\Sales\Quote;
use App\Services\Invoicing\QuoteService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/**
 * MVP-1623 (gl-141): Der Zuständige fürs Angebots-Nachfassen (Feature 112,
 * Vorgabe: Ersteller) lässt sich beim Anlegen und beim Setzen des
 * Nachfasstermins wählen; Versand, Arbeitsliste „Nur meine“ und Erinnerung
 * folgen ihm.
 */
final class QuoteFollowUpOwnerTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private User $admin;

    private User $colleague;

    private Customer $customer;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
        $this->admin = User::factory()->admin()->create(['organization_id' => $this->organization->id, 'name' => 'Anna Admin']);
        $this->colleague = User::factory()->admin()->create(['organization_id' => $this->organization->id, 'name' => 'Ben Vertrieb']);
        $this->customer = Customer::factory()->create(['organization_id' => $this->organization->id]);
        $this->actingAs($this->admin);
    }

    public function test_quote_dialog_sets_the_follow_up_owner_and_sending_keeps_it(): void {
        $this->get(route('quotes.create'))
            ->assertOk()
            ->assertSee('name="follow_up_user_id"', false)
            ->assertSee($this->colleague->sqid, false);

        $this->post(route('quotes.store'), [
            'customer_id' => $this->customer->sqid,
            'follow_up_user_id' => $this->colleague->sqid,
        ])->assertRedirect();

        $quote = Quote::query()->latest('id')->firstOrFail();
        $this->assertSame((int) $this->colleague->id, (int) $quote->follow_up_user_id);

        $quote->update(['status' => QuoteStatus::Approved]);
        app(QuoteService::class)->send($quote->refresh(), $this->admin);

        $quote->refresh();
        $this->assertNotNull($quote->follow_up_at);
        $this->assertSame((int) $this->colleague->id, (int) $quote->follow_up_user_id);
    }

    public function test_without_a_choice_the_creator_follows_up(): void {
        $this->post(route('quotes.store'), ['customer_id' => $this->customer->sqid])->assertRedirect();

        $quote = Quote::query()->latest('id')->firstOrFail();
        $this->assertNull($quote->follow_up_user_id);
        $quote->update(['status' => QuoteStatus::Approved]);
        app(QuoteService::class)->send($quote->refresh(), $this->admin);

        $this->assertSame((int) $this->admin->id, (int) $quote->refresh()->follow_up_user_id);
    }

    public function test_a_user_of_another_organization_is_refused(): void {
        $stranger = User::factory()->user()->create(['organization_id' => Organization::factory()->create()->id]);

        $this->post(route('quotes.store'), [
            'customer_id' => $this->customer->sqid,
            'follow_up_user_id' => $stranger->id,
        ])->assertSessionHasErrors('follow_up_user_id');

        $this->assertSame(0, Quote::query()->count());
    }

    public function test_schedule_dialog_reassigns_the_follow_up_and_the_work_list_follows(): void {
        $quote = Quote::query()->create([
            'organization_id' => $this->organization->id,
            'customer_id' => $this->customer->id,
            'number' => 'A-OWNER-1',
            'status' => QuoteStatus::Sent,
            'created_by' => $this->admin->id,
            'follow_up_at' => now()->subDay()->toDateString(),
        ]);

        $this->get(route('quotes.follow-ups.schedule-dialog', $quote))
            ->assertOk()
            ->assertSee('name="follow_up_user_id"', false)
            ->assertSee('<option value="' . $this->admin->sqid . '" selected', false);

        $this->post(route('quotes.follow-ups.schedule', $quote), [
            'follow_up_at' => now()->toDateString(),
            'follow_up_user_id' => $this->colleague->sqid,
        ])->assertSessionHasNoErrors();
        $this->assertSame((int) $this->colleague->id, (int) $quote->refresh()->follow_up_user_id);

        $this->actingAs($this->colleague);
        $this->assertSame([$quote->id], $this->get(route('quotes.follow-ups.index', ['mine' => 1]))->viewData('due')->pluck('id')->all());
        $this->actingAs($this->admin);
        $this->assertSame([], $this->get(route('quotes.follow-ups.index', ['mine' => 1]))->viewData('due')->pluck('id')->all());
    }
}
