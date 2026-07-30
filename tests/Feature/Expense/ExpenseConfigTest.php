<?php
/*
 * Created on   : Wed Jul 29 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ExpenseConfigTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Expense;

use App\Enums\Expense\PaymentMethod;
use App\Models\{Expense, User};
use App\Services\Expense\ExpenseService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Vollreview W2.3: config/expenses.php ist verdrahtet — Fallback-Kette
 * expenses.* → invoicing.*, Zahlarten-Whitelist und Beleg-Upload-Grenzen.
 */
class ExpenseConfigTest extends TestCase {
    use RefreshDatabase;

    public function test_expense_defaults_prefer_expenses_config(): void {
        config()->set('expenses.default_currency', 'USD');
        config()->set('expenses.default_tax_rate', '7.00');

        $user = User::factory()->admin()->create();
        $this->actingAs($user);

        $expense = app(ExpenseService::class)->create([
            'organization_id' => $user->organization_id,
            'user_id' => $user->id,
            'description' => 'Testbeleg',
            'payment_method' => PaymentMethod::Cash->value,
            'amount_gross' => '11.90',
            'date' => '2026-07-01',
        ]);

        $this->assertSame('USD', $expense->currency->value);
        $this->assertStringStartsWith('7.00', (string) $expense->tax_rate);
    }

    public function test_expense_defaults_fall_back_to_invoicing(): void {
        config()->set('expenses.default_currency', null);
        config()->set('invoicing.default_currency', 'CHF');

        $user = User::factory()->admin()->create();
        $this->actingAs($user);

        $expense = app(ExpenseService::class)->create([
            'organization_id' => $user->organization_id,
            'user_id' => $user->id,
            'description' => 'Testbeleg',
            'payment_method' => PaymentMethod::Cash->value,
            'amount_gross' => '11.90',
            'date' => '2026-07-01',
        ]);

        $this->assertSame('CHF', $expense->currency->value);
    }

    public function test_payment_method_whitelist_is_enforced(): void {
        config()->set('expenses.allowed_payment_methods', [PaymentMethod::Cash->value]);

        $this->assertSame([PaymentMethod::Cash], PaymentMethod::allowed());

        $user = User::factory()->admin()->create();

        $this->actingAs($user)
            ->post(route('expenses.store'), [
                'description' => 'Nicht erlaubte Zahlart',
                'payment_method' => PaymentMethod::BankTransfer->value,
                'amount_gross' => '10.00',
                'date' => '2026-07-01',
            ])
            ->assertSessionHasErrors('payment_method');
    }

    public function test_receipt_upload_respects_expense_mime_whitelist(): void {
        Storage::fake('local');

        $user = User::factory()->admin()->create();
        $expense = Expense::factory()->create([
            'organization_id' => $user->organization_id,
            'user_id' => $user->id,
        ]);

        // ZIP ist global erlaubt, steht aber nicht in der Beleg-Whitelist.
        $zip = UploadedFile::fake()->create('beleg.zip', 40, 'application/zip');
        $this->actingAs($user)
            ->post(route('attachments.store', ['type' => 'expense', 'id' => $expense->sqid]), ['file' => $zip])
            ->assertSessionHasErrors('file');

        $pdf = UploadedFile::fake()->create('beleg.pdf', 40, 'application/pdf');
        $this->actingAs($user)
            ->post(route('attachments.store', ['type' => 'expense', 'id' => $expense->sqid]), ['file' => $pdf])
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('attachments', [
            'attachable_type' => Expense::class,
            'attachable_id' => $expense->id,
            'original_name' => 'beleg.pdf',
        ]);
    }
}
