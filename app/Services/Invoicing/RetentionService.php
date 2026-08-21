<?php
/*
 * Created on   : Thu Aug 20 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : RetentionService.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Invoicing;

use App\Enums\Invoicing\{RetentionBase, RetentionKind, RetentionStatus};
use App\Models\{Invoice, User};
use App\Models\Invoicing\InvoiceRetention;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Sicherheitseinbehalte (Feature 113, MVP-602).
 *
 * **Warum der Einbehalt VOR dem Ausstellen gesetzt werden muss:** Er ist
 * Beleginhalt, kein Lifecycle-Feld. Er steht auf dem PDF („abzüglich 5 %
 * Sicherheitseinbehalt gem. § 17 VOB/B"), und ohne diesen Ausweis ist er
 * rechtlich angreifbar. Ein nachträglich geänderter Einbehalt machte PDF und
 * Datensatz widersprüchlich — genau das, was `MUTABLE_AFTER_ISSUE` verhindert.
 * Nach dem Ausstellen ist nur noch der Lifecycle erlaubt: freigeben oder
 * durch eine Bürgschaft ablösen.
 */
class RetentionService {
    /**
     * Einbehalt anlegen. Bemessung entweder als Prozentsatz ODER als
     * Festbetrag — beides zugleich wäre widersprüchlich.
     */
    public function add(
        Invoice $invoice,
        RetentionKind $kind,
        ?float $percent,
        ?float $fixedAmount,
        ?string $dueOn,
        ?User $actor = null,
        ?string $note = null,
        RetentionBase $baseKind = RetentionBase::Net,
    ): InvoiceRetention {
        if (! $this->isMutable($invoice)) {
            throw new RuntimeException((string) __('invoicing.retention.locked'));
        }
        if (($percent === null) === ($fixedAmount === null)) {
            throw new RuntimeException((string) __('invoicing.retention.needs_one_basis'));
        }

        // Prozentsätze rechnen auf die vereinbarte Grundlage; der Deckel
        // gegen zu hohe Einbehalte bleibt der Bruttobetrag, denn nur der wird
        // tatsächlich überwiesen.
        $gross = round($invoice->total?->toFloat() ?? 0.0, 2);
        $base = $baseKind === RetentionBase::Net
            ? round($invoice->subtotal?->toFloat() ?? 0.0, 2)
            : $gross;
        if ($base <= 0.0 || $gross <= 0.0) {
            throw new RuntimeException((string) __('invoicing.retention.no_total'));
        }

        $amount = $percent !== null ? round($base * $percent / 100, 2) : round((float) $fixedAmount, 2);
        if ($amount <= 0.0) {
            throw new RuntimeException((string) __('invoicing.retention.amount_positive'));
        }

        // Die Summe aller Einbehalte darf den Beleg nicht übersteigen: Ein
        // negativer Zahlbetrag ist keine Sicherheit, sondern ein Rechenfehler.
        if (round($this->openAmountOf($invoice) + $amount, 2) > $gross) {
            throw new RuntimeException((string) __('invoicing.retention.exceeds_total'));
        }

        return DB::transaction(function () use ($invoice, $kind, $percent, $baseKind, $base, $amount, $dueOn, $actor, $note): InvoiceRetention {
            $retention = InvoiceRetention::query()->create([
                'organization_id' => $invoice->organization_id,
                'invoice_id' => $invoice->id,
                'kind' => $kind->value,
                'percent' => $percent,
                'base_kind' => $baseKind->value,
                'base_amount' => $base,
                'amount' => $amount,
                'currency' => $invoice->currency->value,
                'due_on' => $dueOn,
                'status' => RetentionStatus::Open->value,
                'note' => $note,
                'created_by' => $actor?->id,
            ]);

            $invoice->audit('invoice.retention_added', [
                'kind' => $kind->value,
                'base_kind' => $baseKind->value,
                // Rohwerte in den Audit-Payload (Hash-Ketten-Regel): keine
                // gecasteten Money-/Enum-Objekte.
                'amount' => (string) $amount,
                'percent' => $percent === null ? null : (string) $percent,
                'due_on' => $dueOn,
            ]);

            return $retention;
        });
    }

    /** Einbehalt freigeben — ab jetzt ein ganz normaler offener Posten. */
    public function release(InvoiceRetention $retention, ?User $actor = null, ?string $note = null): InvoiceRetention {
        if ($retention->status !== RetentionStatus::Open) {
            throw new RuntimeException((string) __('invoicing.retention.not_open'));
        }

        $retention->forceFill([
            'status' => RetentionStatus::Released->value,
            'released_on' => CarbonImmutable::today()->toDateString(),
            'note' => $note ?? $retention->note,
        ])->save();

        $retention->invoice?->audit('invoice.retention_released', [
            'retention_id' => $retention->id,
            'amount' => (string) $retention->getAttributes()['amount'],
        ]);

        return $retention->refresh();
    }

    /**
     * Offener Einbehalt eines Belegs — die Zahl, um die der fällige Betrag
     * gemindert ist. Überfällige Einbehalte zählen NICHT mehr mit: Ab dem
     * Freigabetermin ist der Betrag fällig, auch wenn niemand den Status
     * gepflegt hat. Sonst wäre ein vergessener Einbehalt eine dauerhafte
     * Mahnsperre.
     */
    public function openAmountOf(Invoice $invoice): float {
        return round((float) $invoice->retentions()
            ->where('status', RetentionStatus::Open->value)
            ->where(function ($query): void {
                $query->whereNull('due_on')->orWhereDate('due_on', '>=', CarbonImmutable::today()->toDateString());
            })
            ->sum('amount'), 2);
    }

    /** Zahlbetrag des Belegs = Summe minus offener Einbehalt. */
    public function payableAmountOf(Invoice $invoice): float {
        return round(($invoice->total?->toFloat() ?? 0.0) - $this->openAmountOf($invoice), 2);
    }

    /** Nur solange der Beleg fachlich änderbar ist (Entwurf/Pro-forma). */
    private function isMutable(Invoice $invoice): bool {
        return $invoice->status === Invoice::STATUS_DRAFT;
    }
}
