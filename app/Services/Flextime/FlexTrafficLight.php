<?php
/*
 * Created on   : Wed Aug 12 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : FlexTrafficLight.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Flextime;

use App\Models\Organization;

/**
 * Ampelphasen für den Gleitzeitsaldo (MVP-521): färbt Kontostände nach
 * org-konfigurierbaren Wertebereichen (symmetrisch für Plus und Minus).
 * Grenzen in Minuten über `organizations.settings.flex.warn_minutes` /
 * `critical_minutes`; Defaults 20 h / 40 h.
 */
final class FlexTrafficLight {
    public const DEFAULT_WARN_MINUTES = 1200;

    public const DEFAULT_CRITICAL_MINUTES = 2400;

    public function __construct(private readonly ?Organization $organization) {}

    public static function current(): self {
        $org = app()->bound('currentOrganization') && app('currentOrganization') instanceof Organization
            ? app('currentOrganization')
            : null;

        return new self($org);
    }

    /** @return array{warn: int, critical: int} */
    public function thresholds(): array {
        $settings = (array) data_get($this->organization?->settings, 'flex', []);
        $warn = max(0, (int) ($settings['warn_minutes'] ?? self::DEFAULT_WARN_MINUTES));
        $critical = max($warn, (int) ($settings['critical_minutes'] ?? self::DEFAULT_CRITICAL_MINUTES));

        return ['warn' => $warn, 'critical' => $critical];
    }

    /** Ampel-Ton (success | warning | error) für einen Saldo in Minuten. */
    public function tone(int $balanceMinutes): string {
        $t = $this->thresholds();
        $abs = abs($balanceMinutes);
        if ($abs >= $t['critical']) {
            return 'error';
        }

        return $abs >= $t['warn'] ? 'warning' : 'success';
    }
}
