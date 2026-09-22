<?php
/*
 * Created on   : Tue Sep 22 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : LexwareFeature.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Enums\Lexoffice;

use App\Enums\Concerns\HasOptions;
use App\Enums\Contracts\HasLabel;

/**
 * Funktionen der Zielmatrix (Feature 158): je Tarif entweder in Lexware
 * enthalten, lokal ergänzbar oder späterer Ausbau. Die ersten vier tragen
 * der erste MVP als lokale Ergänzung aus dem Bestand.
 */
enum LexwareFeature: string implements HasLabel {
    use HasOptions;

    case Invoices = 'invoices';
    case Quotes = 'quotes';
    case Dunning = 'dunning';
    case RecurringInvoices = 'recurring_invoices';
    case PartialFinalInvoices = 'partial_final_invoices';
    case ForeignTaxCases = 'foreign_tax_cases';
    case Accounting = 'accounting';
    case TaxFilings = 'tax_filings';

    public function label(): string {
        return (string) __('lexware.feature.' . $this->value . '.label');
    }

    public function description(): string {
        return (string) __('lexware.feature.' . $this->value . '.description');
    }

    /** Lokale Ergänzung aus dem Bestand — Teil des ersten MVP. */
    public function isLocalMvp(): bool {
        return in_array($this, [self::Invoices, self::Quotes, self::Dunning, self::RecurringInvoices], true);
    }

    /** Einstieg in die lokale Funktion (Routenname), sofern vorhanden. */
    public function localRoute(): ?string {
        return match ($this) {
            self::Invoices => 'billing.feed',
            self::Quotes => 'quotes.index',
            self::Dunning => 'billing.feed',
            self::RecurringInvoices => 'invoice-schedules.index',
            default => null,
        };
    }

    /** @return array<string, string> Routenparameter des Einstiegs */
    public function localRouteParameters(): array {
        return match ($this) {
            self::Invoices => ['tab' => 'outgoing'],
            self::Dunning => ['tab' => 'outgoing', 'overdue' => '1'],
            default => [],
        };
    }
}
