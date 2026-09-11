<?php
/*
 * Created on   : Fri Sep 11 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : LocalInvoiceDraftTarget.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Reselling\Draft;

use App\Enums\Numbering\NumberScope;
use App\Models\{Customer, Invoice, InvoiceItem, Organization, User};
use App\Models\Reselling\ResalePeriod;
use App\Services\Finance\BillingModeResolver;
use App\Services\Invoicing\TaxResolver;
use App\Services\Numbering\NumberSequenceService;
use App\Support\Tz;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\{DB, Route};

/**
 * Lokales Entwurfsziel (Feature 152, MVP-764 / Review 2026-09-11): ein
 * Rechnungsentwurf mit einer Position je Abo und Zeitraum; jede Position
 * trägt Leistungsdatum und Leistungszeitraum der Periode. Nichts wird
 * fakturiert — beim Ausstellen bestätigt der Betreiber die Perioden. Die
 * Positionen sind Bezüge ({@see DraftResult::$morphIds}), der Kern legt die
 * vorgeschlagenen Verknüpfungen an. Gilt bei lokaler Rechnungshoheit.
 */
final class LocalInvoiceDraftTarget implements InvoiceDraftTarget {
    public const KEY = 'local';

    public function __construct(
        private readonly TaxResolver $taxes,
        private readonly NumberSequenceService $numbers,
        private readonly BillingModeResolver $billingModes,
    ) {}

    public function key(): string {
        return self::KEY;
    }

    public function supports(Customer $recipient): bool {
        return ! $this->billingModes->effectiveFor($recipient)->isExternal();
    }

    public function ensureAvailable(Organization $organization, Customer $recipient): void {
        // Lokal gibt es keine Vorbedingung — Nummernkreis und Steuer löst der Entwurf selbst.
    }

    public function draft(Organization $organization, Customer $recipient, array $lines, ?User $user, CarbonImmutable $reference): DraftResult {
        return DB::transaction(function () use ($organization, $recipient, $lines, $user): DraftResult {
            $tax = $this->taxes->resolve($organization, $recipient);
            $now = Tz::now();
            $invoice = Invoice::create([
                'organization_id' => $organization->id,
                'customer_id' => $recipient->id,
                'number' => $this->numbers->next($organization->id, NumberScope::Invoice, $now),
                'status' => Invoice::STATUS_DRAFT,
                'type' => Invoice::TYPE_INVOICE,
                'category' => 'resale',
                'currency' => $recipient->currency,
                'tax_rate' => $tax['rate'],
                'is_reverse_charge' => $tax['reverse_charge'],
                'notes' => $tax['note'],
                'created_by' => $user?->id,
            ]);
            $net = 0.0;
            $position = 0;
            /** @var array<int, int> $morphIds */
            $morphIds = [];
            foreach ($lines as $entry) {
                $position++;
                /** @var ResalePeriod $period */
                $period = $entry['period'];
                $line = $entry['line'];
                // Zeitraum steht in service_from/service_to (eigene Zeile auf Beleg und Seite) — nicht noch einmal im Text.
                $endCustomer = $period->subscription->foreignCustomer;
                $item = $invoice->items()->create([
                    'organization_id' => $organization->id,
                    'service_date' => $period->starts_on->toDateString(),
                    'service_from' => $period->starts_on->toDateString(),
                    'service_to' => $period->ends_on->toDateString(),
                    'description' => $line['name'] . ($endCustomer !== null ? ' · ' . __('resale.draft.end_customer', ['name' => $endCustomer->name]) : ''),
                    'quantity' => (string) $line['quantity'],
                    'unit' => $line['unit_name'],
                    'unit_price' => (string) $line['unit_net'],
                    'tax_category' => $tax['category'],
                    'position' => $position,
                    'article_id' => $period->subscription->article_id,
                ]);
                $morphIds[(int) $period->id] = (int) $item->id;
                $net += $line['quantity'] * $line['unit_net'];
            }
            $invoice->load('items');
            $invoice->recalculate();
            $invoice->save();

            return new DraftResult(
                reference: (string) $invoice->number,
                label: (string) __('resale.draft.local_note', ['number' => $invoice->number]),
                url: Route::has('invoices.show') ? route('invoices.show', $invoice) : null,
                morphClass: (new InvoiceItem)->getMorphClass(),
                morphIds: $morphIds,
                lines: count($lines),
                net: round($net, 2),
            );
        });
    }
}
