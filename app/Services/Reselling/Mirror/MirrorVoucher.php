<?php
/*
 * Created on   : Thu Sep 10 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : MirrorVoucher.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Reselling\Mirror;

use Carbon\CarbonImmutable;

/**
 * Beleg mit allen gespiegelten Positionen (Feature 152, Spiegel-Abstraktion)
 * — für die Rechnungsliste am Abo, die auch Nicht-Lizenzpositionen zeigt.
 * Die Positionen tragen ihre Belegdaten selbst; der Kopf bündelt sie.
 */
final readonly class MirrorVoucher {
    /**
     * @param  list<MirrorLine>  $lines
     */
    public function __construct(
        public string $sourceKey,
        public string $voucherKey,
        public ?string $voucherNumber,
        public ?CarbonImmutable $voucherDate,
        public string $voucherStatus,
        public bool $isCreditNote,
        public ?int $recipientCustomerId,
        public ?string $recipientName,
        public ?string $voucherText,
        public ?string $voucherTextHint,
        public ?CarbonImmutable $serviceFrom,
        public ?CarbonImmutable $serviceTo,
        public ?string $permalink,
        public ?string $previewUrl,
        public array $lines,
    ) {}

    /** Anzeige „dd.mm.yyyy – dd.mm.yyyy" bzw. nur der Beginn. */
    public function servicePeriodLabel(): ?string {
        if ($this->serviceFrom === null) {
            return null;
        }
        $label = $this->serviceFrom->format('d.m.Y');

        return $this->serviceTo === null ? $label : $label . ' – ' . $this->serviceTo->format('d.m.Y');
    }
}
