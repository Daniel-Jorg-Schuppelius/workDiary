<?php
/*
 * Created on   : Thu Nov 12 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ServicePlanColumn.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\ServicePlan\Dto;

use App\Enums\Period\PeriodKind;

/** Eine Leistung als Spalte (Jahressicht) bzw. Zeilenmerkmal (Periodensicht) mit Bezeichnung. */
final readonly class ServicePlanColumn {
    public function __construct(
        public ServiceKey $service,
        public string $label,
        public PeriodKind $kind,
        public int $sort = 0,
    ) {}
}
