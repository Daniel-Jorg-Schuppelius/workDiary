<?php
/*
 * Created on   : Wed Jul 08 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : QuoteService.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Invoicing;

use App\Enums\Numbering\NumberScope;
use App\Models\{Invoice, Quote, User};
use App\Services\Numbering\NumberSequenceService;
use CommonToolkit\Helper\Data\CryptoHelper;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Angebots-Lebenszyklus (Feature 066, MVP-170): interne Freigabe →
 * Versand (mit Portal-Annahme-Token) → Annahme/Teilannahme/Ablehnung/
 * Ablauf; neue Version statt Änderung nach Versand; kontrollierte
 * Überführung in eine Entwurfsrechnung (Snapshot, keine Rückwirkung).
 */
class QuoteService {
    public function __construct(private readonly NumberSequenceService $numbers) {}

    /**
     * @param array<int, array<string, mixed>> $items
     * @param array<string, mixed> $attributes
     */
    public function create(array $attributes, array $items, User $actor): Quote {
        return DB::transaction(function () use ($attributes, $items, $actor): Quote {
            $quote = Quote::query()->create([
                ...$attributes,
                'organization_id' => (int) $actor->organization_id,
                'number' => $this->numbers->next((int) $actor->organization_id, NumberScope::Quote, now()),
                'created_by' => $actor->id,
            ]);

            foreach (array_values($items) as $index => $item) {
                $quote->items()->create([
                    'organization_id' => $quote->organization_id,
                    'position' => $index + 1,
                    ...$item,
                ]);
            }

            $quote->load('items');
            $quote->recalculate();
            $quote->save();
            $quote->audit('quote.created', ['number' => $quote->number]);

            return $quote->refresh();
        });
    }

    /** Neue Version: Nach dem Versand wird nie geändert, sondern versioniert. */
    public function newVersion(Quote $quote, User $actor): Quote {
        if (! in_array($quote->status, ['sent', 'rejected', 'expired'], true)) {
            throw new \RuntimeException((string) __('Nur versendete/abgelehnte/abgelaufene Angebote werden versioniert.'));
        }

        return DB::transaction(function () use ($quote, $actor): Quote {
            $next = $quote->replicate(['acceptance_token_hash', 'decided_at', 'decision_snapshot']);
            $next->version = $quote->version + 1;
            $next->previous_version_id = (int) $quote->id;
            $next->status = 'draft';
            $next->created_by = (int) $actor->id;
            $next->save();

            foreach ($quote->items as $item) {
                $copy = $item->replicate(['accepted']);
                $copy->quote_id = $next->id;
                $copy->save();
            }

            $next->audit('quote.versioned', ['from_version' => $quote->version]);

            return $next->refresh();
        });
    }

    public function approve(Quote $quote, User $actor): Quote {
        if ($quote->status !== 'draft') {
            throw new \RuntimeException((string) __('Nur Entwürfe können freigegeben werden.'));
        }
        $quote->update(['status' => 'approved']);
        $quote->audit('quote.approved', ['by' => $actor->id]);

        return $quote->refresh();
    }

    /**
     * Versand: erzeugt das Portal-Annahme-Token (nur Hash gespeichert).
     *
     * @return array{quote: Quote, acceptance_token: string}
     */
    public function send(Quote $quote, User $actor): array {
        if (! in_array($quote->status, ['approved'], true)) {
            throw new \RuntimeException((string) __('Nur freigegebene Angebote können versendet werden.'));
        }

        $token = Str::random(48);
        $quote->update([
            'status' => 'sent',
            'acceptance_token_hash' => CryptoHelper::hash($token),
        ]);
        $quote->audit('quote.sent', ['by' => $actor->id]);

        return ['quote' => $quote->refresh(), 'acceptance_token' => $token];
    }

    /**
     * Annahme (auch Teilannahme über item-IDs): friert den angenommenen
     * Stand als decision_snapshot ein — spätere Änderungen wirken nie zurück.
     *
     * @param array<int, int>|null $acceptedItemIds null = Vollannahme
     */
    public function accept(Quote $quote, ?array $acceptedItemIds = null, ?string $token = null): Quote {
        if ($quote->isExpired()) {
            $quote->update(['status' => 'expired']);
            throw new \RuntimeException((string) __('Die Bindefrist ist abgelaufen.'));
        }
        if ($quote->status !== 'sent') {
            throw new \RuntimeException((string) __('Nur versendete Angebote können angenommen werden.'));
        }
        if ($token !== null && CryptoHelper::hash($token) !== $quote->acceptance_token_hash) {
            throw new \RuntimeException((string) __('Ungültiges Annahme-Token.'));
        }

        return DB::transaction(function () use ($quote, $acceptedItemIds): Quote {
            $partial = false;
            foreach ($quote->items as $item) {
                $accepted = $acceptedItemIds === null
                    ? ! $item->optional // Vollannahme: Optionen bleiben draußen
                    : in_array((int) $item->id, $acceptedItemIds, true);
                $item->update(['accepted' => $accepted]);
                if (! $accepted && ! $item->optional) {
                    $partial = true;
                }
            }

            $quote->load('items');
            $quote->recalculate();
            $quote->status = $partial ? 'partially_accepted' : 'accepted';
            $quote->decided_at = now();
            $quote->decision_snapshot = [
                'items' => $quote->items->map(fn($item): array => [
                    'id' => $item->id,
                    'description' => $item->description,
                    'quantity' => (float) $item->quantity,
                    'unit' => $item->unit,
                    'unit_price' => $item->unit_price?->toFloat(),
                    'discount_percent' => $item->discount_percent !== null ? (float) $item->discount_percent->getNumericValue() : null,
                    'discount_amount' => $item->discount_amount?->toFloat(),
                    'tax_rate' => $item->tax_rate?->getNumericValue(),
                    'accepted' => (bool) $item->accepted,
                ])->all(),
                'total' => $quote->total?->toFloat(),
                'decided_at' => now()->toIso8601String(),
            ];
            $quote->save();
            $quote->audit('quote.accepted', ['partial' => $partial]);

            return $quote->refresh();
        });
    }

    public function reject(Quote $quote, ?string $reason = null): Quote {
        if ($quote->status !== 'sent') {
            throw new \RuntimeException((string) __('Nur versendete Angebote können abgelehnt werden.'));
        }
        $quote->update(['status' => 'rejected', 'decided_at' => now()]);
        $quote->audit('quote.rejected', ['reason' => $reason]);

        return $quote->refresh();
    }

    /**
     * Kontrollierte Überführung in eine ENTWURFS-Rechnung: nur angenommene
     * Positionen aus dem eingefrorenen decision_snapshot; das Angebot
     * bleibt unverändert (kein stiller Rückfluss).
     */
    public function convertToInvoice(Quote $quote, User $actor): Invoice {
        if (! in_array($quote->status, ['accepted', 'partially_accepted'], true)) {
            throw new \RuntimeException((string) __('Nur angenommene Angebote werden überführt.'));
        }

        return DB::transaction(function () use ($quote, $actor): Invoice {
            // Steuerkontext wie im InvoiceGenerator über den TaxResolver (§19, Reverse Charge, Drittland) statt
            // hartkodierter 19 % — sonst unrichtiger Steuerausweis nach § 14c UStG (Whitebox 2026-07-10, G3).
            $tax = app(TaxResolver::class)->resolve($quote->organization()->firstOrFail(), $quote->customer()->firstOrFail());
            $notes = (string) __('Gemäß Angebot :number (Version :version)', ['number' => $quote->number, 'version' => $quote->version]);
            if ($tax['note'] !== null) {
                $notes .= "\n" . $tax['note'];
            }
            // Bei 0-%-Kontext (§19/RC/Drittland) gelten keine Positionssätze — der Kopfsatz 0,00 bestimmt alle Positionen.
            $suppressItemRates = $tax['reverse_charge'] || (float) $tax['rate'] === 0.0;

            $invoice = Invoice::query()->create([
                'organization_id' => $quote->organization_id,
                'customer_id' => $quote->customer_id,
                'project_id' => $quote->project_id,
                'quote_id' => $quote->id,
                'number' => $this->numbers->next((int) $quote->organization_id, NumberScope::Invoice, now()),
                'status' => Invoice::STATUS_DRAFT,
                'type' => Invoice::TYPE_INVOICE,
                'tax_rate' => $tax['rate'],
                'is_reverse_charge' => $tax['reverse_charge'],
                'notes' => $notes,
                'created_by' => $actor->id,
            ]);

            $position = 0;
            foreach ((array) ($quote->decision_snapshot['items'] ?? []) as $item) {
                if (! ($item['accepted'] ?? false)) {
                    continue;
                }
                // Alt-Snapshots (vor MVP-416-Nachtrag) kennen unit/discount_* nicht → null;
                // invoice_items.unit ist NOT NULL (Default 'h'), daher nur setzen wenn vorhanden.
                $payload = [
                    'organization_id' => $quote->organization_id,
                    'description' => (string) $item['description'],
                    'quantity' => (string) $item['quantity'],
                    'unit_price' => (string) $item['unit_price'],
                    'discount_percent' => $item['discount_percent'] ?? null,
                    'discount_amount' => $item['discount_amount'] ?? null,
                    'tax_rate' => $suppressItemRates ? null : $item['tax_rate'],
                    'position' => ++$position,
                ];
                if (($item['unit'] ?? null) !== null) {
                    $payload['unit'] = (string) $item['unit'];
                }
                $invoice->items()->create($payload);
            }

            $invoice->load('items');
            $invoice->recalculate();
            $invoice->save();

            $quote->audit('quote.converted', ['invoice_id' => $invoice->id]);

            return $invoice->refresh();
        });
    }

    /**
     * Pro-forma → echte Rechnung (MVP-171): NEUE Nummer aus dem
     * Rechnungskreis, Typwechsel, voller Ausstellungs-Weg danach —
     * die Pro-forma selbst bleibt unangetastet.
     */
    public function proformaToInvoice(Invoice $proforma, User $actor): Invoice {
        if ($proforma->type !== Invoice::TYPE_PROFORMA) {
            throw new \RuntimeException((string) __('Nur Pro-forma-Rechnungen können umgewandelt werden.'));
        }

        return DB::transaction(function () use ($proforma, $actor): Invoice {
            $invoice = Invoice::query()->create([
                'organization_id' => $proforma->organization_id,
                'customer_id' => $proforma->customer_id,
                'project_id' => $proforma->project_id,
                'parent_invoice_id' => $proforma->id,
                'number' => $this->numbers->next((int) $proforma->organization_id, NumberScope::Invoice, now()),
                'status' => Invoice::STATUS_DRAFT,
                'type' => Invoice::TYPE_INVOICE,
                'tax_rate' => $proforma->tax_rate,
                'is_reverse_charge' => (bool) $proforma->is_reverse_charge,
                'notes' => (string) __('Aus Pro-forma :number', ['number' => $proforma->number]),
                'created_by' => $actor->id,
            ]);

            $position = 0;
            foreach ($proforma->items as $item) {
                $invoice->items()->create([
                    'organization_id' => $proforma->organization_id,
                    'description' => $item->description,
                    'quantity' => (string) $item->quantity,
                    'unit' => $item->unit,
                    'unit_price' => $item->unit_price,
                    'discount_percent' => $item->discount_percent,
                    'discount_amount' => $item->discount_amount,
                    'tax_rate' => $item->tax_rate,
                    'position' => ++$position,
                ]);
            }

            $invoice->load('items');
            $invoice->recalculate();
            $invoice->save();

            $proforma->audit('invoice.proforma_converted', ['invoice_id' => $invoice->id]);

            return $invoice->refresh();
        });
    }
}
