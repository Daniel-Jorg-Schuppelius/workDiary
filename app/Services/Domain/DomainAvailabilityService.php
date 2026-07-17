<?php
/*
 * Created on   : Thu Jul 16 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : DomainAvailabilityService.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Services\Domain;

use App\Enums\Domain\DomainCapabilityArea;
use App\Models\Domain\DomainProviderConnection;
use App\Plugins\DomainReselling\DomainResellingConfig;
use App\Plugins\Support\Domain\DomainRateBudgetException;
use CommonToolkit\Helper\Data\CryptoHelper;
use Illuminate\Support\Facades\Cache;

/**
 * Verfügbarkeitsprüfung (Feature 083, MVP-388). Schützt das dokumentierte
 * Strafpunktmodell der Bulkprüfung durch ein organisationsbezogenes
 * Stundenbudget, Debouncing und einen Ergebnis-Cache. Premium-/Aftermarket-/
 * EAP-/Trademark-Ergebnisse werden mit Preis/Währung getrennt ausgewiesen.
 */
class DomainAvailabilityService {
    public function __construct(private readonly DomainProviderResolver $resolver) {}

    /**
     * Prüft eine Liste von Domainnamen. Gecachte Treffer verbrauchen kein
     * Budget; erschöpftes Budget wirft {@see DomainRateBudgetException}.
     *
     * @param  list<string>  $domains
     * @return list<array{domain: string, available: bool, premium: bool, price: ?float, currency: ?string, class: ?string, cached: bool}>
     */
    public function check(DomainProviderConnection $connection, array $domains): array {
        $config = DomainResellingConfig::resolve();
        $ttl = $config['check_cache_ttl'];
        $results = [];
        $toQuery = [];

        foreach (array_values(array_unique(array_map('strtolower', $domains))) as $domain) {
            $cached = Cache::get($this->cacheKey($connection, $domain));
            if (is_array($cached)) {
                /** @var array<string, mixed> $cached */
                $results[$domain] = $this->entryShape($cached, true);
            } else {
                $toQuery[] = $domain;
            }
        }

        if ($toQuery !== []) {
            $this->consumeBudget($connection, count($toQuery));

            $adapter = $this->resolver->for($connection);
            $params = [];
            foreach ($toQuery as $i => $domain) {
                $params['domain' . $i] = $domain;
            }
            $response = $adapter->execute('CheckDomains', $params, DomainCapabilityArea::Domains);

            foreach ($response->rows() as $i => $row) {
                $domain = $toQuery[$i] ?? ($row['domain'] ?? null);
                if ($domain === null) {
                    continue;
                }
                $entry = [
                    'domain' => $domain,
                    'available' => $this->isAvailable($row),
                    'premium' => $this->flag($row, ['premium', 'aftermarket']),
                    'price' => isset($row['price']) && is_numeric($row['price']) ? (float) $row['price'] : null,
                    'currency' => $row['currency'] ?? null,
                    'class' => $row['class'] ?? ($row['reason'] ?? null),
                ];
                Cache::put($this->cacheKey($connection, $domain), $entry, $ttl);
                $results[$domain] = $this->entryShape($entry, false);
            }
        }

        // Reihenfolge der Eingabe erhalten.
        $ordered = [];
        foreach (array_values(array_unique(array_map('strtolower', $domains))) as $domain) {
            if (isset($results[$domain])) {
                $ordered[] = $results[$domain];
            }
        }

        return $ordered;
    }

    /**
     * Normalisiert einen Roh-/Cache-Eintrag auf die typisierte Ergebnisform.
     *
     * @param  array<string, mixed>  $data
     * @return array{domain: string, available: bool, premium: bool, price: ?float, currency: ?string, class: ?string, cached: bool}
     */
    private function entryShape(array $data, bool $cached): array {
        return [
            'domain' => (string) ($data['domain'] ?? ''),
            'available' => (bool) ($data['available'] ?? false),
            'premium' => (bool) ($data['premium'] ?? false),
            'price' => isset($data['price']) && is_numeric($data['price']) ? (float) $data['price'] : null,
            'currency' => isset($data['currency']) ? (string) $data['currency'] : null,
            'class' => isset($data['class']) ? (string) $data['class'] : null,
            'cached' => $cached,
        ];
    }

    /** @param  array<string, string>  $row */
    private function isAvailable(array $row): bool {
        $status = strtolower($row['status'] ?? $row['availability'] ?? '');

        return in_array($status, ['available', 'free', '210', '1', 'true'], true);
    }

    /**
     * @param  array<string, string>  $row
     * @param  list<string>  $keys
     */
    private function flag(array $row, array $keys): bool {
        foreach ($keys as $key) {
            $value = strtolower($row[$key] ?? '');
            if (in_array($value, ['1', 'true', 'yes'], true)) {
                return true;
            }
        }

        return false;
    }

    private function consumeBudget(DomainProviderConnection $connection, int $units): void {
        $config = DomainResellingConfig::resolve();
        $key = sprintf('domain:checkbudget:%d:%s', (int) $connection->organization_id, date('YmdH'));
        $used = (int) Cache::get($key, 0);
        if ($used + $units > $config['check_budget_per_hour']) {
            throw new DomainRateBudgetException();
        }
        Cache::put($key, $used + $units, 3600);
    }

    private function cacheKey(DomainProviderConnection $connection, string $domain): string {
        return sprintf('domain:check:%d:%s', $connection->id, CryptoHelper::hash($domain));
    }
}
