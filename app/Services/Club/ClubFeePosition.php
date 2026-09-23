<?php
/*
 * Created on   : Wed Sep 23 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ClubFeePosition.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Club;

use App\Enums\Club\ClubFeePositionKind;
use Carbon\CarbonImmutable;
use CommonToolkit\ValueObjects\Money;

/**
 * Berechnete Beitragsposition (MVP-849): fachliche Quelle + Periode ergeben den
 * Schlüssel, über den der Beitragslauf (MVP-850) Doppelungen ausschließt.
 * Beträge sind Money, je Position auf Cent gerundet.
 */
final class ClubFeePosition {
    /**
     * @param  array<string, mixed>  $basis  Berechnungsgrund für Vorschau und Mitteilung
     */
    public function __construct(
        public readonly ClubFeePositionKind $kind,
        public readonly string $sourceKey,
        public readonly int $accountId,
        public readonly ?int $memberId,
        public readonly string $label,
        public readonly CarbonImmutable $periodStart,
        public readonly CarbonImmutable $periodEnd,
        public readonly CarbonImmutable $dueOn,
        public readonly Money $amount,
        public readonly array $basis = [],
    ) {}

    /** @return array<string, mixed> */
    public function toArray(): array {
        return [
            'kind' => $this->kind->value,
            'source_key' => $this->sourceKey,
            'account_id' => $this->accountId,
            'member_id' => $this->memberId,
            'label' => $this->label,
            'period_start' => $this->periodStart->toDateString(),
            'period_end' => $this->periodEnd->toDateString(),
            'due_on' => $this->dueOn->toDateString(),
            'amount' => $this->amount->getAmount(),
            'currency' => $this->amount->getCurrency()->value,
            'basis' => $this->basis,
        ];
    }
}
