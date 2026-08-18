<?php
/*
 * Created on   : Tue Aug 18 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ExpenseVoucherPushTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Feature\Expense;

use App\Enums\Expense\{ExpenseStatus, PaymentMethod};
use App\Models\{Expense, ExpenseCategory, LexofficeVoucher, PluginSetting, User};
use App\Plugins\Lexoffice\{LexofficeMapper, LexofficePlugin, LexofficeService};
use App\Services\Billing\{DocumentLinks, ExpenseVoucherPush};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\PermissionRegistrar;
use Tests\Concerns\WithOrganization;
use Tests\Support\FakePluginHttp;
use Tests\TestCase;

/**
 * Aktiver Auslagen-Belegpush (Feature 106).
 *
 * Kern der Prüfung: **Der Push ist terminal** — nur genehmigte Auslagen, ohne
 * Kategorie-Zuordnung kein Push, ein zweiter Klick erzeugt keinen zweiten
 * Beleg, und die entstandene Verknüpfung lässt sich nicht lösen.
 */
final class ExpenseVoucherPushTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private User $admin;

    private ExpenseCategory $category;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
        app(PermissionRegistrar::class)->setPermissionsTeamId($this->organization->id);
        $this->admin = User::factory()->admin()->create(['organization_id' => $this->organization->id]);

        PluginSetting::query()->create([
            'organization_id' => $this->organization->id,
            'plugin_id' => LexofficePlugin::ID,
            'enabled' => true,
            'settings' => ['api_key' => 'test-key'],
        ]);
        app()->instance(LexofficeService::class, new LexofficeService('test-key', new LexofficeMapper));

        $this->category = ExpenseCategory::query()->create([
            'organization_id' => $this->organization->id,
            'slug' => 'fuel',
            'label' => 'Kraftstoff',
            'color' => 'primary',
            'accounting_category_id' => 'cat-uuid-1',
        ]);
    }

    private function expense(string $status = 'approved', ?int $categoryId = null): Expense {
        return Expense::query()->create([
            'organization_id' => $this->organization->id,
            'user_id' => $this->admin->id,
            'expense_category_id' => $categoryId ?? $this->category->id,
            'date' => '2026-08-10',
            'vendor' => 'Tankstelle Nord',
            'description' => 'Diesel Firmenwagen',
            'payment_method' => PaymentMethod::PrivatePaid->value,
            'currency' => 'EUR',
            'amount_net' => '50.00',
            'tax_rate' => '19.00',
            'tax_amount' => '9.50',
            'amount_gross' => '59.50',
            'status' => $status,
        ]);
    }

    private function fakeCreate(): FakePluginHttp {
        return FakePluginHttp::fake([
            'https://api.lexoffice.io/v1/vouchers*' => FakePluginHttp::response([
                'id' => 'voucher-new-1',
                'resourceUri' => 'https://api.lexoffice.io/v1/vouchers/voucher-new-1',
            ], 200),
        ]);
    }

    public function test_push_creates_the_voucher_and_links_it(): void {
        $this->fakeCreate();
        $expense = $this->expense();

        $this->actingAs($this->admin)
            ->post(route('expenses.push-voucher', $expense))
            ->assertRedirect();

        $voucher = LexofficeVoucher::query()->firstOrFail();
        $this->assertSame('voucher-new-1', $voucher->external_id);
        $this->assertSame('purchaseinvoice', $voucher->voucher_type);

        // Die Verknüpfung trägt das pushed-Kennzeichen: aktiver Push, keine
        // nachträgliche Zuordnung.
        $this->assertNotNull(app(DocumentLinks::class)->voucherFor($expense));
        $this->assertTrue(app(ExpenseVoucherPush::class)->wasPushed($expense));
    }

    /** Der zweite Klick findet den Beleg des ersten — kein zweiter Beleg. */
    public function test_push_is_idempotent(): void {
        $this->fakeCreate();
        $expense = $this->expense();
        $push = app(ExpenseVoucherPush::class);

        $first = $push->push($expense);
        $second = $push->push($expense);

        $this->assertSame($first->id, $second->id);
        $this->assertSame(1, LexofficeVoucher::query()->count());
    }

    /** Der Push ist unwiderruflich — die Freigabe steht davor. */
    public function test_unapproved_expense_is_refused(): void {
        $this->fakeCreate();
        $expense = $this->expense(ExpenseStatus::Draft->value);

        $this->expectException(\RuntimeException::class);
        app(ExpenseVoucherPush::class)->push($expense);
    }

    /** Ohne Kategorie-Zuordnung kein Push — Fehlermeldung statt Rateweg. */
    public function test_missing_category_mapping_is_refused(): void {
        $this->fakeCreate();
        $bare = ExpenseCategory::query()->create([
            'organization_id' => $this->organization->id,
            'slug' => 'misc',
            'label' => 'Sonstiges',
            'color' => 'neutral',
        ]);
        $expense = $this->expense(categoryId: (int) $bare->id);

        $this->expectException(\RuntimeException::class);
        app(ExpenseVoucherPush::class)->push($expense);
    }

    /** Der Beleg existiert unwiderruflich — die Verknüpfung bleibt. */
    public function test_pushed_link_cannot_be_removed(): void {
        $this->fakeCreate();
        $expense = $this->expense();
        app(ExpenseVoucherPush::class)->push($expense);

        $this->actingAs($this->admin)
            ->delete(route('expenses.unlink-voucher', $expense))
            ->assertRedirect()
            ->assertSessionHas('error');

        $this->assertNotNull(app(DocumentLinks::class)->voucherFor($expense));
    }

    /** Eine bloß ZUGEORDNETE Verknüpfung (MVP-551) bleibt dagegen lösbar. */
    public function test_manually_linked_voucher_can_still_be_unlinked(): void {
        $expense = $this->expense();
        $voucher = LexofficeVoucher::query()->create([
            'organization_id' => $this->organization->id,
            'external_id' => 'voucher-manual',
            'voucher_type' => 'purchaseinvoice',
            'currency' => 'EUR',
        ]);
        app(DocumentLinks::class)->link($expense, $voucher);

        $this->actingAs($this->admin)
            ->delete(route('expenses.unlink-voucher', $expense))
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertNull(app(DocumentLinks::class)->voucherFor($expense));
    }
}
