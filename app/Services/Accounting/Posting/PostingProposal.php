<?php
/*
 * Created on   : Fri Aug 21 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : PostingProposal.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Accounting\Posting;

use App\Enums\Finance\PostingSourceKind;
use Carbon\CarbonImmutable;
use CommonToolkit\Enums\RoundingMode;
use CommonToolkit\Helper\Data\NumberHelper;
use Illuminate\Database\Eloquent\Model;

/**
 * Buchungsvorschlag zu genau einer Quelle (Feature 125, MVP-673).
 *
 * Ein Vorschlag ist entweder vollständig oder blockiert — nie „ungefähr
 * richtig". Fehlt ein Konten- oder Steuer-Mapping, steht der Grund in
 * {@see $blockers} und der Vorschlag wird nicht buchbar. Auf ein
 * Standardkonto zu raten wäre der teurere Fehler: Er fällt erst bei der
 * Auswertung auf, und dann ist die Buchung festgeschrieben.
 */
final class PostingProposal {
    /**
     * @param  list<PostingProposalLine>  $lines
     * @param  list<string>  $blockers
     * @param  array<string, mixed>  $extra  Zusatzangaben für den Snapshot (z. B. der
     *                                       auszugleichende Beleg bei einer Zahlung)
     */
    public function __construct(
        public readonly PostingSourceKind $kind,
        public readonly Model $source,
        public readonly string $sourceKey,
        public readonly CarbonImmutable $bookedOn,
        public readonly string $memo,
        public readonly array $lines = [],
        public readonly array $blockers = [],
        public readonly ?CarbonImmutable $documentOn = null,
        public readonly ?string $documentReference = null,
        public readonly ?string $ruleVersion = null,
        public readonly ?string $title = null,
        public readonly array $extra = [],
    ) {}

    /** Nur mit Zeilen und ohne Blocker darf gebucht werden. */
    public function isPostable(): bool {
        return $this->blockers === [] && $this->lines !== [];
    }

    /** @return numeric-string */
    public function debitTotal(): string {
        return NumberHelper::sumPrecise(array_map(static fn (PostingProposalLine $line): string => $line->debit, $this->lines), 2, RoundingMode::HalfUp);
    }

    /** @return numeric-string */
    public function creditTotal(): string {
        return NumberHelper::sumPrecise(array_map(static fn (PostingProposalLine $line): string => $line->credit, $this->lines), 2, RoundingMode::HalfUp);
    }

    /** @return list<array<string, mixed>> */
    public function lineData(): array {
        return array_map(fn (PostingProposalLine $line): array => $line->toLineData(), $this->lines);
    }

    /**
     * Nachweis-Snapshot: Betrag, Konten, Steuer, Regelversion und Quelle —
     * die Abnahmebedingung des Pakets in einer Struktur.
     *
     * @return array<string, mixed>
     */
    public function toSnapshot(): array {
        return [
            'source_kind' => $this->kind->value,
            'source_key' => $this->sourceKey,
            'source_type' => $this->source::class,
            'source_id' => $this->source->getKey(),
            'booked_on' => $this->bookedOn->toDateString(),
            'document_reference' => $this->documentReference,
            'rule_version' => $this->ruleVersion,
            'debit_total' => $this->debitTotal(),
            'credit_total' => $this->creditTotal(),
            'lines' => array_map(fn (PostingProposalLine $line): array => $line->toExplanation(), $this->lines),
        ] + $this->extra;
    }
}
