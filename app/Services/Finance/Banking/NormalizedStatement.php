<?php
/*
 * Created on   : Sat Jun 13 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : NormalizedStatement.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Finance\Banking;

use App\Enums\Finance\BankStatementFormat;

/**
 * Formatneutraler Bankauszug (Feature 045). Ein Auszug bündelt Salden, Zeitraum
 * und die zugehörigen {@see NormalizedTransaction}.
 */
final class NormalizedStatement {
    /** @param list<NormalizedTransaction> $transactions */
    public function __construct(
        public readonly BankStatementFormat $format,
        public readonly ?string $accountIban,
        public readonly ?float $openingBalance,
        public readonly ?float $closingBalance,
        public readonly ?string $periodFrom,
        public readonly ?string $periodTo,
        public readonly array $transactions,
    ) {}
}
