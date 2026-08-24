<?php
/*
 * Created on   : Sun Aug 23 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : AgingBuckets.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Support\Billing;

/**
 * DIE Fälligkeits-Bänderung (Vollscan 2026-08-23, B15/E7): vorher zwei
 * unabhängige Definitionen — Faktura-Bericht 7/14/30 (Float im Controller)
 * und OPOS 30/60/90 (Money). Entschieden (E7): EINE Klasse, zwei Presets;
 * beide behalten ihre historischen Bucket-Keys (Übersetzungen/Views).
 */
final class AgingBuckets {
    /**
     * @param list<int> $limits aufsteigende Obergrenzen in Tagen überfällig
     * @param list<string> $keys Bucket-Keys: [nicht fällig, …je Limit…, darüber]
     */
    private function __construct(
        public readonly array $limits,
        public readonly array $keys,
    ) {}

    /** OPOS-/Buchhaltungs-Bänderung: 30/60/90 (AccountingOpenItem). */
    public static function accounting(): self {
        return new self([30, 60, 90], ['not_due', 'd30', 'd60', 'd90', 'd90plus']);
    }

    /** Faktura-Bericht: 7/14/30 (BillingReport-Aging). */
    public static function billing(): self {
        return new self([7, 14, 30], ['current', '1_7', '8_14', '15_30', '30_plus']);
    }

    /**
     * Bucket-Key für „Tage überfällig" (null oder ≤ 0 ⇒ nicht fällig).
     * Grenzen sind einschließlich: age ≤ limit ⇒ dieses Band.
     */
    public function bucketFor(?int $daysOverdue): string {
        if ($daysOverdue === null || $daysOverdue <= 0) {
            return $this->keys[0];
        }
        foreach ($this->limits as $i => $limit) {
            if ($daysOverdue <= $limit) {
                return $this->keys[$i + 1];
            }
        }

        return $this->keys[count($this->keys) - 1];
    }

    /** @return array<string, array{count: int, total: float}> leeres Zähl-Gerüst in Key-Reihenfolge */
    public function emptyCounts(): array {
        $buckets = [];
        foreach ($this->keys as $key) {
            $buckets[$key] = ['count' => 0, 'total' => 0.0];
        }

        return $buckets;
    }
}
