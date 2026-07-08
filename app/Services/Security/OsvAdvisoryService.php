<?php
/*
 * Created on   : Tue Jul 07 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : OsvAdvisoryService.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Security;

use App\Models\SecurityAdvisory;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * OSV-Abfrage für die installierten Abhängigkeiten (Rang 70, Fakten AR §21):
 * `POST /v1/querybatch` mit purl inkl. Version — die Antwort enthält damit
 * nur Advisories, die die installierte Version tatsächlich betreffen; eine
 * eigene Range-Auswertung entfällt. Details werden per `GET /v1/vulns/{id}`
 * nachgeladen (nur für neue oder geänderte IDs — `modified` dient als
 * Cache-Schlüssel). Advisories, die nicht mehr zurückkommen (Paket
 * aktualisiert), werden als `resolved` markiert statt gelöscht.
 */
class OsvAdvisoryService {
    private const BASE_URL = 'https://api.osv.dev/v1';

    /** OSV-Limit: max. 1000 Queries je Batch; konservativ chunken. */
    private const BATCH_SIZE = 500;

    /**
     * @return array{checked: int, open: int, new: int, resolved: int}
     */
    public function pull(): array {
        $packages = $this->installedPackages();
        if ($packages === []) {
            throw new RuntimeException('Keine Lock-Dateien gefunden (composer.lock/package-lock.json).');
        }

        $found = [];
        foreach (array_chunk($packages, self::BATCH_SIZE) as $chunk) {
            $queries = array_map(
                static fn(array $p): array => ['package' => ['purl' => $p['purl']]],
                $chunk,
            );

            $response = Http::timeout(30)
                ->acceptJson()
                ->post(self::BASE_URL . '/querybatch', ['queries' => $queries]);
            if ($response->failed()) {
                throw new RuntimeException('OSV querybatch fehlgeschlagen (HTTP ' . $response->status() . ').');
            }

            /** @var array<int, array{vulns?: array<int, array{id?: string, modified?: string}>}> $results */
            $results = (array) $response->json('results', []);
            foreach ($results as $index => $result) {
                $package = $chunk[$index] ?? null;
                if ($package === null) {
                    continue;
                }
                foreach ((array) ($result['vulns'] ?? []) as $vuln) {
                    if (! isset($vuln['id'])) {
                        continue;
                    }
                    $found[(string) $vuln['id']] = [
                        'package' => $package,
                        'modified' => isset($vuln['modified']) ? (string) $vuln['modified'] : null,
                    ];
                }
            }
        }

        $known = SecurityAdvisory::query()->where('source', 'osv')->get()->keyBy('external_id');

        $new = 0;
        foreach ($found as $id => $hit) {
            /** @var SecurityAdvisory|null $existing */
            $existing = $known->get($id);

            $modified = $hit['modified'] !== null ? \Carbon\CarbonImmutable::parse($hit['modified']) : null;
            $unchanged = $existing !== null
                && $existing->resolved_at === null
                && $existing->modified_at !== null
                && $modified !== null
                && $existing->modified_at->equalTo($modified);
            if ($unchanged) {
                continue;
            }

            $details = $this->fetchDetails($id);
            $payload = [
                'source' => 'osv',
                'external_id' => $id,
                'ecosystem' => $hit['package']['ecosystem'],
                'package' => $hit['package']['name'],
                'installed_version' => $hit['package']['version'],
                'severity' => $details['severity'],
                'cvss_vector' => $details['cvss_vector'],
                'summary' => $details['summary'],
                'fixed_in' => $details['fixed_in'],
                'modified_at' => $modified,
                'resolved_at' => null, // wieder offen, falls Regression
            ];

            if ($existing === null) {
                SecurityAdvisory::query()->create($payload);
                $new++;
            } else {
                $existing->update($payload);
            }
        }

        // Nicht mehr gemeldete Advisories als behoben markieren.
        $resolved = SecurityAdvisory::query()
            ->where('source', 'osv')
            ->whereNull('resolved_at')
            ->whereNotIn('external_id', array_keys($found))
            ->update(['resolved_at' => now()]);

        return [
            'checked' => count($packages),
            'open' => SecurityAdvisory::query()->whereNull('resolved_at')->count(),
            'new' => $new,
            'resolved' => (int) $resolved,
        ];
    }

    /**
     * Installierte Pakete aus composer.lock (nur prod) und package-lock.json
     * (ohne dev-Abhängigkeiten), jeweils mit versionierter purl.
     *
     * @return list<array{ecosystem: string, name: string, version: string, purl: string}>
     */
    public function installedPackages(): array {
        $packages = [];

        $composerLock = base_path('composer.lock');
        if (is_file($composerLock)) {
            /** @var array{packages?: array<int, array{name?: string, version?: string}>} $lock */
            $lock = (array) json_decode((string) file_get_contents($composerLock), true);
            foreach ((array) ($lock['packages'] ?? []) as $package) {
                if (! isset($package['name'], $package['version'])) {
                    continue;
                }
                $version = ltrim((string) $package['version'], 'v');
                $packages[] = [
                    'ecosystem' => 'composer',
                    'name' => (string) $package['name'],
                    'version' => $version,
                    'purl' => 'pkg:composer/' . $package['name'] . '@' . $version,
                ];
            }
        }

        $npmLock = base_path('package-lock.json');
        if (is_file($npmLock)) {
            /** @var array{packages?: array<string, array{version?: string, dev?: bool}>} $lock */
            $lock = (array) json_decode((string) file_get_contents($npmLock), true);
            foreach ((array) ($lock['packages'] ?? []) as $path => $package) {
                if ($path === '' || ($package['dev'] ?? false) === true) {
                    continue;
                }
                if (! isset($package['version'])) {
                    continue;
                }
                $name = (string) preg_replace('#^.*node_modules/#', '', $path);
                if ($name === '') {
                    continue;
                }
                $packages[] = [
                    'ecosystem' => 'npm',
                    'name' => $name,
                    'version' => (string) $package['version'],
                    'purl' => 'pkg:npm/' . str_replace('@', '%40', $name) . '@' . $package['version'],
                ];
            }
        }

        return $packages;
    }

    /**
     * Details eines Advisories (Severity/Vektor/Summary/fixed_in).
     *
     * @return array{severity: string, cvss_vector: ?string, summary: ?string, fixed_in: ?string}
     */
    private function fetchDetails(string $id): array {
        $response = Http::timeout(30)->acceptJson()->get(self::BASE_URL . '/vulns/' . $id);
        if ($response->failed()) {
            return ['severity' => 'unknown', 'cvss_vector' => null, 'summary' => null, 'fixed_in' => null];
        }

        /** @var array<string, mixed> $data */
        $data = (array) $response->json();

        $severity = 'unknown';
        $dbSeverity = data_get($data, 'database_specific.severity');
        if (is_string($dbSeverity) && $dbSeverity !== '') {
            $severity = match (strtolower($dbSeverity)) {
                'critical' => 'critical',
                'high' => 'high',
                'moderate', 'medium' => 'medium',
                'low' => 'low',
                default => 'unknown',
            };
        }

        $vector = null;
        foreach ((array) ($data['severity'] ?? []) as $entry) {
            if (is_array($entry) && isset($entry['score']) && is_string($entry['score'])) {
                $vector = substr($entry['score'], 0, 150);
                break;
            }
        }

        $fixedIn = null;
        foreach ((array) ($data['affected'] ?? []) as $affected) {
            foreach ((array) data_get($affected, 'ranges', []) as $range) {
                foreach ((array) data_get($range, 'events', []) as $event) {
                    if (is_array($event) && isset($event['fixed'])) {
                        $fixedIn = (string) $event['fixed'];
                        break 3;
                    }
                }
            }
        }

        $summary = data_get($data, 'summary');

        return [
            'severity' => $severity,
            'cvss_vector' => $vector,
            'summary' => is_string($summary) ? mb_substr($summary, 0, 500) : null,
            'fixed_in' => $fixedIn,
        ];
    }
}
