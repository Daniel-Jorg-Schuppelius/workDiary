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
use App\Plugins\Support\Domain\DomainRateBudgetException;
use App\Services\Domain\Concerns\ReadsProviderFields;
use CommonToolkit\Helper\Data\{CryptoHelper, StringHelper};
use Illuminate\Support\Facades\Cache;

/**
 * Verfügbarkeitsprüfung (Feature 083, MVP-388). Schützt das dokumentierte
 * Strafpunktmodell der Bulkprüfung durch ein organisationsbezogenes
 * Stundenbudget, Debouncing und einen Ergebnis-Cache. Premium-/Aftermarket-/
 * EAP-/Trademark-Ergebnisse werden mit Preis/Währung getrennt ausgewiesen.
 */
class DomainAvailabilityService {
    use ReadsProviderFields;

    public function __construct(private readonly DomainProviderResolver $resolver) {}

    /**
     * Prüft eine Liste von Domainnamen. Gecachte Treffer verbrauchen kein
     * Budget; erschöpftes Budget wirft {@see DomainRateBudgetException}.
     *
     * @param  list<string>  $domains
     * @return list<array{domain: string, available: bool, premium: bool, price: ?float, currency: ?string, class: ?string, cached: bool}>
     */
    public function check(DomainProviderConnection $connection, array $domains): array {
        $config = $this->resolver->settings((int) $connection->organization_id);
        $ttl = $config->checkCacheTtl;
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
                $price = self::providerField($row, ['xfeeamount', 'price', 'aftermarketprice']);
                $entry = [
                    'domain' => $domain,
                    'available' => $this->isAvailable($row),
                    'premium' => $this->flag($row, ['premium', 'aftermarket']) || self::providerField($row, ['xfeeclass', 'aftermarketinfo']) !== null,
                    'price' => $price !== null && is_numeric($price) ? (float) $price : null,
                    'currency' => self::providerField($row, ['xfeecurrency', 'currency', 'aftermarketcurrency']),
                    'class' => self::providerField($row, ['xfeeclass', 'class', 'reason']),
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
        // Doku: DOMAINCHECK = „(CODE) (DESCRIPTION)“, 210 = frei, 211 = vergeben.
        $status = strtolower((string) self::providerField($row, ['domaincheck', 'status', 'availability']));
        if (str_starts_with($status, '210')) {
            return true;
        }
        if (str_starts_with($status, '211') || str_contains($status, 'not available')) {
            return false;
        }

        return in_array($status, ['available', 'free', '1', 'true'], true);
    }

    /**
     * @param  array<string, string>  $row
     * @param  list<string>  $keys
     */
    private function flag(array $row, array $keys): bool {
        foreach ($keys as $key) {
            if (StringHelper::parseBool($row[$key] ?? null) === true) {
                return true;
            }
        }

        return false;
    }

    private function consumeBudget(DomainProviderConnection $connection, int $units): void {
        $config = $this->resolver->settings((int) $connection->organization_id);
        $key = sprintf('domain:checkbudget:%d:%s', (int) $connection->organization_id, date('YmdH'));
        $used = (int) Cache::get($key, 0);
        if ($used + $units > $config->checkBudgetPerHour) {
            throw new DomainRateBudgetException();
        }
        Cache::put($key, $used + $units, 3600);
    }

    private function cacheKey(DomainProviderConnection $connection, string $domain): string {
        return sprintf('domain:check:%d:%s', $connection->id, CryptoHelper::hash($domain));
    }
}
