<?php
/*
 * Created on   : Thu Sep 03 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : SuccessionLink.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Reselling\Marketplace;

/**
 * Eine Telekom-Position, die von einem Quality-Hosting-Vertrag abgelöst wurde:
 * `predecessor` trägt bereits das gekappte Ende.
 */
final readonly class SuccessionLink {
    public function __construct(
        public MarketplaceEntitlement $predecessor,
        public MarketplaceEntitlement $successor,
    ) {}
}
