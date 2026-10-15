<?php
/*
 * Created on   : Thu Oct 15 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : InvoiceInquiryFactory.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Database\Factories\Invoicing;

use App\Enums\Invoicing\{InvoiceInquiryMatchKind, InvoiceInquiryStatus};
use App\Models\Invoicing\{Invoice, InvoiceInquiry};
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<InvoiceInquiry>
 */
class InvoiceInquiryFactory extends Factory {
    protected $model = InvoiceInquiry::class;

    /** @return array<string, mixed> */
    public function definition(): array {
        return [
            'organization_id' => null, // explizit setzen (Mandant)
            'invoice_id' => null,
            'invoice_number' => 'R-' . fake()->unique()->numerify('######'),
            'message_id' => '<' . fake()->unique()->uuid() . '@example.test>',
            'from_email' => fake()->safeEmail(),
            'from_name' => fake()->name(),
            'subject' => 'Rückfrage zur Rechnung',
            'received_at' => now(),
            'match_kind' => InvoiceInquiryMatchKind::Number,
            'status' => InvoiceInquiryStatus::Open,
        ];
    }

    /** Nachfrage zu einer lokalen Rechnung (Organisation, Kunde und Nummer aus der Rechnung). */
    public function forInvoice(Invoice $invoice): static {
        return $this->state(fn (): array => [
            'organization_id' => $invoice->organization_id,
            'invoice_id' => $invoice->id,
            'customer_id' => $invoice->customer_id,
            'invoice_number' => (string) ($invoice->number ?? $invoice->external_number ?? ''),
        ]);
    }

    /** Nachfrage zu einem Beleg des Buchhaltungsprogramms. */
    public function external(string $source, string $key, string $number): static {
        return $this->state(fn (): array => [
            'invoice_id' => null,
            'external_source' => $source,
            'external_key' => $key,
            'invoice_number' => $number,
        ]);
    }

    public function answered(): static {
        return $this->state(fn (): array => [
            'status' => InvoiceInquiryStatus::Answered,
            'answered_at' => now(),
        ]);
    }
}
