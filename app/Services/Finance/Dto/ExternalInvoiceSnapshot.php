<?php
/*
 * Created on   : Sat Nov 07 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ExternalInvoiceSnapshot.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Finance\Dto;

use App\Enums\Finance\{ExternalInvoiceFormat, ExternalInvoiceStatus, ProviderSendState};
use Carbon\CarbonImmutable;

/**
 * Stand einer extern ausgestellten Rechnung, wie ihn der Anbieter meldet
 * (Feature 196). Beträge sind Dezimalzeichenketten mit Punkt.
 */
final readonly class ExternalInvoiceSnapshot {
    /**
     * @param  list<array{name: string, quantity: string, unit_price: string|null, net: string}>  $lines
     * @param  array<string, mixed>  $extra  anbieterspezifische Angaben für den Abruf des Originals
     */
    public function __construct(
        public string $externalId,
        public ExternalInvoiceStatus $status,
        public ?string $rawStatus,
        public ?string $number,
        public ?CarbonImmutable $issuedOn,
        public string $kind,
        public string $currency,
        public ?string $netAmount,
        public ?string $grossAmount,
        public array $lines,
        public ProviderSendState $sent = ProviderSendState::Unknown,
        public ?CarbonImmutable $sentAt = null,
        public ?ExternalInvoiceFormat $formatHint = null,
        public array $extra = [],
    ) {}
}
