<?php
/*
 * Created on   : Thu Nov 12 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : BankBookingSuggestion.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Accounting\Posting;

use App\Enums\Finance\BookingSuggestionBasis;
use App\Models\Accounting\{AccountingAccount, AccountingTaxCode};
use Carbon\CarbonImmutable;

/**
 * Kontierungsvorschlag für einen Bankumsatz ohne Beleg (Feature 207, MVP-1660).
 *
 * Er sagt, wie die Organisation einen gleichartigen Umsatz zuletzt selbst
 * gebucht hat, und warum er gleichartig ist. Gebucht wird er nur auf
 * ausdrückliche Bestätigung — der Vorschlag selbst schreibt nichts.
 */
final readonly class BankBookingSuggestion {
    /** Hinweis: Das Konto der letzten Buchung ist stillgelegt. */
    public const string WARNING_ACCOUNT_INACTIVE = 'account_inactive';

    /** Hinweis: Das Steuerkennzeichen gilt am Buchungstag nicht mehr. */
    public const string WARNING_TAX_CODE_INVALID = 'tax_code_invalid';

    /**
     * @param  int  $matches  frühere Buchungen mit genau dieser Kontierung
     * @param  int  $total  frühere Buchungen mit demselben Merkmal
     * @param  numeric-string  $lastAmount  Betrag des zuletzt so gebuchten Umsatzes (Decimal-String)
     * @param  int  $learnedEntryId  jüngste Buchung, aus der der Vorschlag stammt
     */
    public function __construct(
        public int $transactionId,
        public BookingSuggestionBasis $basis,
        public AccountingAccount $account,
        public ?AccountingTaxCode $taxCode,
        public ?int $costCenterId,
        public string $note,
        public int $matches,
        public int $total,
        public CarbonImmutable $lastBookedOn,
        public string $lastAmount,
        public bool $deviates,
        public ?string $warning,
        public int $learnedEntryId,
    ) {}

    /**
     * Stärke aus Merkmal und Anzahl: Gläubiger-ID und Mandatsreferenz wiegen
     * dreifach, die IBAN doppelt, Stichwörter einfach; ab drei gleichen
     * Buchungen zählt jede weitere nicht mehr.
     */
    public function strength(): string {
        $score = $this->basis->weight() * min($this->matches, 3);

        return match (true) {
            $score >= 6 => 'strong',
            $score >= 3 => 'medium',
            default => 'weak',
        };
    }

    public function strengthTone(): string {
        return match ($this->strength()) {
            'strong' => 'success',
            'medium' => 'info',
            default => 'neutral',
        };
    }

    /** Begründung, etwa „3-mal so gebucht, zuletzt 03/2026, gleiche Mandatsreferenz“. */
    public function reason(): string {
        $replace = [
            'count' => $this->matches,
            'total' => $this->total,
            'month' => $this->lastBookedOn->format('m/Y'),
            'basis' => (string) __('accounting.suggestions.basis.' . $this->basis->value),
        ];

        return $this->matches === $this->total
            ? (string) __('accounting.suggestions.reason', $replace)
            : (string) __('accounting.suggestions.reason_mixed', $replace);
    }

    /** Ohne Abweichung und ohne Hinweis lässt sich der Vorschlag gebündelt bestätigen. */
    public function confirmable(): bool {
        return ! $this->deviates && $this->warning === null;
    }

    /**
     * Gruppe gleichartiger Umsätze: gleiches Merkmal (über die jüngste Buchung,
     * aus der der Vorschlag stammt) und gleiche Kontierung. Ändert sich der
     * Lernstand zwischen Anzeige und Bestätigung, ändert sich der Schlüssel —
     * die Bestätigung bucht dann nichts Ungesehenes.
     */
    public function groupKey(): string {
        return implode('-', [
            $this->basis->value,
            $this->learnedEntryId,
            (int) $this->account->id,
            (int) ($this->taxCode->id ?? 0),
            (int) ($this->costCenterId ?? 0),
        ]);
    }
}
