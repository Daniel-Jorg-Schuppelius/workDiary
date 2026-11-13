<?php
/*
 * Created on   : Thu Nov 12 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ServicePlanQuery.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\ServicePlan\Dto;

use Carbon\CarbonImmutable;

/**
 * Abfrage des Leistungsplans (Feature 199): Sicht, Jahr und Filter.
 *
 * - `responsibleIds`: nur Zeilen mit einer Zelle dieser Personen (null = alle);
 *   `unassigned`: nur Zeilen mit einer Zelle ohne Zuständigkeit.
 * - `visibleToIds`: Sicht ohne Leserecht des Plans — Zellen anderer Personen
 *   bleiben verdeckt und zählen nicht (eigene und vertretene Personen, MVP-1648).
 * - `services`: Auswahl der Leistungen (Spalten bzw. Zeilen; leer = alle).
 */
final readonly class ServicePlanQuery {
    public const string VIEW_YEAR = 'year';

    public const string VIEW_PERIODS = 'periods';

    /**
     * @param  list<int>|null  $responsibleIds
     * @param  list<int>|null  $visibleToIds
     * @param  list<ServiceKey>  $services
     * @param  list<int>|null  $customerIds
     */
    public function __construct(
        public string $view,
        public int $year,
        public CarbonImmutable $today,
        public ?array $responsibleIds = null,
        public bool $unassigned = false,
        public ?array $visibleToIds = null,
        public ?string $state = null,
        public ?int $customerGroupId = null,
        public ?string $search = null,
        public array $services = [],
        public ?array $customerIds = null,
        public ?string $ruleSet = null,
    ) {}

    public function isYearView(): bool {
        return $this->view === self::VIEW_YEAR;
    }
}
