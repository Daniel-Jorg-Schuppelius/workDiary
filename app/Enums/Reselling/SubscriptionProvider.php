<?php
/*
 * Created on   : Fri Sep 04 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : SubscriptionProvider.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Enums\Reselling;

use App\Enums\Concerns\HasOptions;
use App\Enums\Contracts\HasLabel;

/**
 * Anbieter (Einkaufsseite) eines Abos. Datei-Importe und Plugins tragen
 * ihren Anbieter ein; „manual" ist die Pflege von Hand.
 */
enum SubscriptionProvider: string implements HasLabel {
    use HasOptions;

    case TelekomMarketplace = 'telekom_marketplace';
    case QualityHosting = 'qualityhosting';
    case DomainReselling = 'domainreselling';
    case Manual = 'manual';
    case Other = 'other';

    public function label(): string {
        return (string) __('resale.provider.' . $this->value);
    }
}
