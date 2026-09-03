<?php
/*
 * Created on   : Thu Sep 03 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : MarketplaceEntitlement.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Reselling\Marketplace;

use App\Enums\Reselling\BillingFrequency;
use Carbon\CarbonImmutable;
use CommonToolkit\ValueObjects\Money;

/**
 * Eine Abo-Position aus einem Reseller-Export: ein Entitlement/Vertrag mit
 * Laufzeit und der Gebühr, die der Anbieter dem Reseller je Rhythmus
 * berechnet. `endsOn` null = läuft bis auf Weiteres (Quality Hosting: „Aktiv,
 * verlängert sich"). `quantity`/`unitFee` sind gesetzt, wenn die Quelle sie
 * nennt; sonst leitet der `UnitPriceCatalog` sie aus der Gebühr ab.
 */
final readonly class MarketplaceEntitlement {
    public const SOURCE_TELEKOM = 'telekom';

    public const SOURCE_QUALITYHOSTING = 'qualityhosting';

    public function __construct(
        public MarketplaceCompany $company,
        public string $entitlementId,
        public string $orderId,
        public string $application,
        public string $edition,
        public Money $fee,
        public BillingFrequency $frequency,
        public CarbonImmutable $startsOn,
        public ?CarbonImmutable $endsOn,
        public string $status,
        public int $assignedUsers,
        public int $sourceLine,
        public string $source = self::SOURCE_TELEKOM,
        public ?int $quantity = null,
        public ?Money $unitFee = null,
        public string $successionNote = '',
        public ?int $termMonths = null,
    ) {}

    /**
     * Vertragslaufzeit in Monaten; ohne Angabe die Länge eines Rhythmus-Schritts.
     */
    public function termMonths(): int {
        if ($this->termMonths !== null && $this->termMonths > 0) {
            return $this->termMonths;
        }

        return $this->frequency === BillingFrequency::Monthly ? 1 : 12;
    }

    public function isOpenEnded(): bool {
        return $this->endsOn === null;
    }

    public function isRunningOn(CarbonImmutable $date): bool {
        return $this->startsOn->lessThanOrEqualTo($date) && ($this->endsOn === null || $this->endsOn->greaterThan($date));
    }

    public function sourceLabel(): string {
        return self::labelFor($this->source);
    }

    public static function labelFor(string $source): string {
        return match ($source) {
            self::SOURCE_TELEKOM => 'Telekom',
            self::SOURCE_QUALITYHOSTING => 'Quality Hosting',
            default => $source,
        };
    }

    public function withEndsOn(CarbonImmutable $endsOn, string $successionNote): self {
        return new self(
            company: $this->company,
            entitlementId: $this->entitlementId,
            orderId: $this->orderId,
            application: $this->application,
            edition: $this->edition,
            fee: $this->fee,
            frequency: $this->frequency,
            startsOn: $this->startsOn,
            endsOn: $endsOn,
            status: $this->status,
            assignedUsers: $this->assignedUsers,
            sourceLine: $this->sourceLine,
            source: $this->source,
            quantity: $this->quantity,
            unitFee: $this->unitFee,
            successionNote: $successionNote,
            termMonths: $this->termMonths,
        );
    }

    public function withCompany(MarketplaceCompany $company): self {
        return new self(
            company: $company,
            entitlementId: $this->entitlementId,
            orderId: $this->orderId,
            application: $this->application,
            edition: $this->edition,
            fee: $this->fee,
            frequency: $this->frequency,
            startsOn: $this->startsOn,
            endsOn: $this->endsOn,
            status: $this->status,
            assignedUsers: $this->assignedUsers,
            sourceLine: $this->sourceLine,
            source: $this->source,
            quantity: $this->quantity,
            unitFee: $this->unitFee,
            successionNote: $this->successionNote,
            termMonths: $this->termMonths,
        );
    }
}
