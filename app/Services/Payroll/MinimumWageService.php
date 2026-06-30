<?php
/*
 * Created on   : Thu Jun 04 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : MinimumWageService.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Services\Payroll;

use App\Models\MinimumWage;
use Carbon\{CarbonImmutable, CarbonInterface};

/**
 * Liefert den für ein Datum gültigen gesetzlichen Mindestlohn (Gültig-ab-
 * Historie) sowie davon abgeleitete Größen (Minijob-Verdienstgrenze).
 */
class MinimumWageService {
    /** @var array<string, float|null> In-Request-Cache: "orgId|date" → Satz */
    private array $cache = [];

    /**
     * Höchster Mindestlohn-Satz, dessen `valid_from` am Stichtag bereits gilt.
     * Ohne Org-Angabe greift der Organization-Scope (currentOrganization).
     */
    public function currentFor(?CarbonInterface $date = null, ?int $organizationId = null): ?float {
        $date ??= CarbonImmutable::today();
        $key = ($organizationId ?? 'scope') . '|' . $date->toDateString();
        if (array_key_exists($key, $this->cache)) {
            return $this->cache[$key];
        }

        $row = MinimumWage::query()
            ->when($organizationId !== null, fn($q) => $q->withoutGlobalScopes()->where('organization_id', $organizationId))
            ->whereDate('valid_from', '<=', $date->toDateString())
            ->orderByDesc('valid_from')
            ->first(['hourly_amount']);

        return $this->cache[$key] = $row !== null ? (float) $row->hourly_amount : null;
    }

    /**
     * Monatliche Minijob-Verdienstgrenze: gesetzlich Mindestlohn × 130 / 3,
     * auf volle Euro gerundet (§ 8 SGB IV). Null, wenn kein Mindestlohn hinterlegt.
     */
    public function minijobMonthlyLimit(?CarbonInterface $date = null, ?int $organizationId = null): ?int {
        $rate = $this->currentFor($date, $organizationId);

        return $rate === null ? null : (int) round($rate * 130 / 3);
    }
}
