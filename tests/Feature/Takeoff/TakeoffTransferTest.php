<?php
/*
 * Created on   : Fri Oct 02 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : TakeoffTransferTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Takeoff;

use App\Enums\Gaeb\BoqItemStatus;
use App\Enums\Sync\SyncCommandStatus;
use App\Enums\Takeoff\TakeoffStatus;
use App\Models\Customer\Customer;
use App\Models\Diary\DiaryEntry;
use App\Models\Gaeb\{BillOfQuantity, BoqItem, BoqItemProgress};
use App\Models\Invoicing\Invoice;
use App\Models\Platform\User;
use App\Models\Project\Project;
use App\Models\Sales\Quote;
use App\Models\Takeoff\{Takeoff, TakeoffLine};
use App\Services\Billing\DocumentChainService;
use App\Services\Takeoff\TakeoffService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/** MVP-1059: Aufmaß in Angebot, Rechnung und LV-Leistungsstand; Belegkette; Offline-Zeile mit Foto. */
class TakeoffTransferTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private User $admin;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization(['name' => 'Aufmaß Übernahme']);
        $this->admin = User::factory()->admin()->create(['organization_id' => $this->organization->id]);
        $this->actingAs($this->admin);
    }

    private function completedTakeoff(): Takeoff {
        $customer = Customer::factory()->create(['organization_id' => $this->organization->id]);
        $entry = DiaryEntry::factory()->create(['organization_id' => $this->organization->id, 'user_id' => $this->admin->id, 'customer_id' => $customer->id]);
        $takeoff = Takeoff::query()->create(['organization_id' => $this->organization->id, 'diary_entry_id' => $entry->id, 'title' => 'Bad OG']);
        $service = app(TakeoffService::class);
        $service->saveLine($takeoff, ['formula' => '04', 'values' => ['3', '2,5'], 'description' => 'Wandfliesen', 'unit' => 'm2']);
        $service->saveLine($takeoff, ['formula' => '04', 'values' => ['0,8', '2'], 'factor' => '-1', 'description' => 'Wandfliesen', 'unit' => 'm2']);
        $service->transition($takeoff, TakeoffStatus::Completed, $this->admin);

        return $takeoff->fresh();
    }

    public function test_quantities_become_a_quote_once(): void {
        $takeoff = $this->completedTakeoff();

        $this->post(route('takeoffs.transfer', $takeoff), ['kind' => 'quote'])->assertRedirect();

        $quote = Quote::query()->firstOrFail();
        $this->assertSame(1, $quote->items()->count());
        $this->assertEqualsWithDelta(5.9, (float) $quote->items()->first()->quantity, 0.0001);
        $this->assertSame('Wandfliesen', $quote->items()->first()->description);
        $this->assertDatabaseHas('takeoff_transfers', ['takeoff_id' => $takeoff->id, 'kind' => 'quote', 'target_id' => $quote->id]);

        $this->post(route('takeoffs.transfer', $takeoff), ['kind' => 'quote'])->assertForbidden();
        $this->assertSame(1, Quote::query()->count());
    }

    public function test_invoice_draft_carries_the_takeoff_pdf(): void {
        Storage::fake(config('filesystems.default'));
        $takeoff = $this->completedTakeoff();

        $this->post(route('takeoffs.transfer', $takeoff), ['kind' => 'invoice'])->assertRedirect();

        $invoice = Invoice::query()->firstOrFail();
        $this->assertSame(1, $invoice->items()->count());
        $this->assertSame(1, $invoice->documents()->count());
    }

    public function test_boq_lines_are_reported_as_progress_and_the_chain_lists_open_takeoffs(): void {
        $bill = BillOfQuantity::factory()->create(['organization_id' => $this->organization->id, 'status' => BoqItemStatus::Ordered->value]);
        $item = BoqItem::factory()->create(['organization_id' => $this->organization->id, 'bill_of_quantity_id' => $bill->id, 'unit' => 'm2']);
        $takeoff = Takeoff::query()->create(['organization_id' => $this->organization->id, 'bill_of_quantity_id' => $bill->id, 'title' => 'Abschnitt 1']);
        app(TakeoffService::class)->saveLine($takeoff, ['formula' => '04', 'values' => ['10', '2'], 'boq_item_id' => $item->id]);
        app(TakeoffService::class)->transition($takeoff, TakeoffStatus::Completed, $this->admin);

        $chain = collect(app(DocumentChainService::class)->groups($this->organization, $this->admin))->keyBy('key');
        $this->assertSame(1, $chain['takeoffs_to_bill']['count']);

        $this->post(route('takeoffs.transfer', $takeoff), ['kind' => 'progress'])->assertRedirect(route('takeoffs.show', $takeoff));

        $this->assertEqualsWithDelta(20.0, (float) BoqItemProgress::query()->where('boq_item_id', $item->id)->value('quantity'), 0.0001);
        $chain = collect(app(DocumentChainService::class)->groups($this->organization, $this->admin))->keyBy('key');
        $this->assertSame(0, $chain['takeoffs_to_bill']['count']);
    }

    public function test_offline_line_is_applied_and_its_photo_follows(): void {
        Storage::fake(config('filesystems.default'));
        $entry = DiaryEntry::factory()->create(['organization_id' => $this->organization->id, 'user_id' => $this->admin->id]);
        $takeoff = Takeoff::query()->create(['organization_id' => $this->organization->id, 'diary_entry_id' => $entry->id, 'title' => 'Flur']);
        $uuid = (string) Str::uuid();

        $this->postJson(route('api.internal.sync.commands'), ['commands' => [[
            'client_uuid' => $uuid,
            'type' => 'takeoff.line',
            'payload' => ['takeoff' => $takeoff->sqid, 'formula' => '04', 'values' => ['4,2', '1,5', ''], 'factor' => '1', 'label' => 'Flur', 'pending_files' => ['photo']],
        ]]])->assertOk()->assertJsonPath('results.0.status', SyncCommandStatus::Applied->value);

        $this->assertSame('6.3000', (string) TakeoffLine::query()->where('takeoff_id', $takeoff->id)->value('quantity'));

        $this->post(route('api.internal.sync.attachments'), [
            'client_uuid' => $uuid,
            'field' => 'photo',
            'file' => UploadedFile::fake()->image('flur.jpg'),
        ])->assertOk()->assertJsonPath('status', 'stored');
        $this->assertSame(1, $takeoff->attachments()->count());
    }

    public function test_carrier_pages_list_the_takeoffs_and_the_sheet_offers_transfers(): void {
        $takeoff = $this->completedTakeoff();
        $project = Project::factory()->create(['organization_id' => $this->organization->id]);
        $takeoff->update(['project_id' => $project->id]);
        $bill = BillOfQuantity::factory()->create(['organization_id' => $this->organization->id]);
        Takeoff::query()->create(['organization_id' => $this->organization->id, 'bill_of_quantity_id' => $bill->id, 'title' => 'LV-Aufmaß Nord']);

        $this->get(route('takeoffs.show', $takeoff))->assertOk()
            ->assertSee(__('takeoff.transfer.kind.quote'))
            ->assertSee(__('takeoff.transfer.kind.invoice'))
            ->assertDontSee(__('takeoff.transfer.kind.progress'));
        $this->get(route('diary.show', $takeoff->diaryEntry))->assertOk()->assertSee('Bad OG');
        $this->get(route('projects.show', [$project, 'tab' => 'diary']))->assertOk()->assertSee('Bad OG');
        $this->get(route('bill-of-quantities.show', $bill))->assertOk()->assertSee('LV-Aufmaß Nord');
    }
}
