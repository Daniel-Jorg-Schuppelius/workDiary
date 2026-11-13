<?php
/*
 * Created on   : Thu Nov 12 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : LoginNetworks.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Auth\LoginPolicy;

use CommonToolkit\Helper\Data\IPHelper;
use CommonToolkit\Helper\Shell;
use Illuminate\Support\Facades\{Cache, Log};

/**
 * Erlaubte Netze einer Organisation (Feature 205, MVP-1655): IPv4/IPv6 als
 * Adresse oder CIDR sowie DNS-Namen dynamischer Anschlüsse.
 *
 * DNS-Namen löst nie die Anmeldung auf, sondern der Scheduler (alle fünf
 * Minuten, im Hintergrund) bzw. ein Job nach dem Speichern der Richtlinie —
 * je Name mit hartem Zeitlimit, höchstens {@see MAX_HOSTNAMES} Namen je
 * Organisation. Das Ergebnis liegt als Array im Cache (Cache-Konvention).
 * Scheitert eine Auflösung, bleibt die zuletzt erfolgreich aufgelöste Adresse
 * höchstens {@see STALE_SECONDS} lang gültig (ein kurzer DNS-Ausfall sperrt
 * kein Büro aus), danach erlaubt der Name nichts mehr. Ein noch nie
 * aufgelöster Name erlaubt nichts.
 *
 * Nicht öffentlich routbare Adressen aus DNS-Antworten werden verworfen —
 * sonst ließe ein Name, der auf 127.0.0.1 oder ein internes Netz zeigt, bei
 * einem Proxy-Fehler jeden herein. Ausnahme nur, wenn der Betreiber es für
 * Vor-Ort-Installationen mit internem DNS erlaubt
 * (`security.login_policy.dns_allow_private`, Vorgabe aus). Ausdrücklich
 * eingetragene Netze (CIDR) gelten unverändert.
 *
 * Die Client-Adresse kommt ausschließlich aus `Request::ip()` und damit aus
 * der vorhandenen TrustedProxies-Konfiguration — nie aus eigenen Headern.
 */
class LoginNetworks {
    public const int MAX_ENTRIES = 50;

    /** Höchstens so viele DNS-Namen je Organisation (Sicherheitsprüfung M3). */
    public const int MAX_HOSTNAMES = 10;

    /** Zuletzt erfolgreich aufgelöste Adressen gelten so lange weiter. */
    public const int STALE_SECONDS = 86400;

    /** Form des Cache-Werts geändert (Zeitpunkt je Name) → Schlüssel versioniert. */
    private const string CACHE_PREFIX = 'login-networks:2:';

    private const int CACHE_SECONDS = 172800;

    /** Hartes Zeitlimit je Auflösung in Sekunden. */
    private const float LOOKUP_TIMEOUT = 3.0;

    private const string HOST_PATTERN = '/^(?=.{4,253}$)(?:[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z](?:[a-z0-9-]{0,61}[a-z0-9])?$/';

    /** Eingabe vereinheitlichen: Leerraum, Großschreibung und abschließenden Punkt entfernen. */
    public static function normalizeEntry(string $entry): string {
        return rtrim(mb_strtolower(trim($entry)), '.');
    }

    public static function isHostname(string $entry): bool {
        return ! IPHelper::isValidIP($entry) && ! str_contains($entry, '/') && preg_match(self::HOST_PATTERN, $entry) === 1;
    }

    /** Adresse, CIDR (v4 /1–32, v6 /1–128; /0 = „alle“ ist keine Einschränkung) oder DNS-Name. */
    public static function isValidEntry(string $entry): bool {
        if (IPHelper::isValidIP($entry)) {
            return true;
        }
        if (str_contains($entry, '/')) {
            [$range, $prefix] = explode('/', $entry, 2);
            if (! IPHelper::isValidIP($range) || preg_match('/^\d{1,3}$/', $prefix) !== 1) {
                return false;
            }

            return (int) $prefix >= 1 && (int) $prefix <= (IPHelper::isIPv6($range) ? 128 : 32);
        }

        return self::isHostname($entry);
    }

    /**
     * Adresse des Clients in der Form, die die Liste vergleicht: IPv4 in
     * IPv6-Hülle (`::ffff:192.0.2.1`) zählt als IPv4.
     */
    public static function clientIp(?string $ip): ?string {
        $ip = trim((string) $ip);
        if (str_starts_with(mb_strtolower($ip), '::ffff:')) {
            $inner = substr($ip, 7);
            if (IPHelper::isIPv4($inner)) {
                return $inner;
            }
        }

        return IPHelper::isValidIP($ip) ? $ip : null;
    }

    /** Öffentlich routbar? Private, Loopback-, Link-Local- und reservierte Adressen nicht. */
    public static function isPublic(?string $ip): bool {
        $ip = self::clientIp($ip);

        return $ip !== null && IPHelper::isPublicIP($ip);
    }

    /** Adresse für die Anzeige gekürzt: IPv4 auf /24, IPv6 auf /48 (Sicherheitsprüfung N9). */
    public static function masked(?string $ip): ?string {
        $ip = self::clientIp($ip);
        if ($ip === null) {
            return null;
        }
        $prefix = IPHelper::isIPv6($ip) ? 48 : 24;

        return IPHelper::getNetworkAddress($ip, $prefix) . '/' . $prefix;
    }

    /**
     * Liegt die Adresse in einem der Netze der Organisation?
     *
     * @param  list<string>  $entries
     */
    public function contains(int $organizationId, array $entries, ?string $ip): bool {
        $ip = self::clientIp($ip);
        if ($ip === null || $entries === []) {
            return false;
        }

        $resolved = null;
        foreach ($entries as $entry) {
            if (self::isHostname($entry)) {
                $resolved ??= $this->resolvedHosts($organizationId);
                foreach ($resolved[$entry] ?? [] as $address) {
                    if (self::matches($ip, $address)) {
                        return true;
                    }
                }

                continue;
            }
            if (self::matches($ip, $entry)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Gültige Adressen der DNS-Namen aus dem Cache (nie DNS): nur, was in den
     * letzten {@see STALE_SECONDS} erfolgreich aufgelöst wurde.
     *
     * @return array<string, list<string>>
     */
    public function resolvedHosts(int $organizationId): array {
        $hosts = [];
        foreach ($this->cachedHosts($organizationId) as $host => $entry) {
            if (now()->getTimestamp() - $entry['resolved_at'] <= self::STALE_SECONDS) {
                $hosts[$host] = $entry['addresses'];
            }
        }

        return $hosts;
    }

    /**
     * Für die Oberfläche: nur öffentliche Adressen; interne zählen nur mit
     * (Sicherheitsprüfung M3 — interne Adressen nicht anzeigen).
     *
     * @return array<string, array{public: list<string>, internal: int}>
     */
    public function displayHosts(int $organizationId): array {
        $display = [];
        foreach ($this->resolvedHosts($organizationId) as $host => $addresses) {
            $public = array_values(array_filter($addresses, self::isPublic(...)));
            $display[$host] = ['public' => $public, 'internal' => count($addresses) - count($public)];
        }

        return $display;
    }

    /** Zeitpunkt der letzten Auflösungsrunde (Unix-Zeit) oder null. */
    public function resolvedAt(int $organizationId): ?int {
        $cached = Cache::get(self::CACHE_PREFIX . $organizationId);

        return is_array($cached) && is_int($cached['checked_at'] ?? null) ? $cached['checked_at'] : null;
    }

    /**
     * DNS-Namen der Liste auflösen und im Cache ablegen (Scheduler, Job nach
     * dem Speichern). Ohne DNS-Namen wird der Eintrag verworfen.
     *
     * @param  list<string>  $entries
     * @return array<string, list<string>> gültige Adressen je Name
     */
    public function refresh(int $organizationId, array $entries): array {
        $hostnames = array_slice(array_values(array_unique(array_filter($entries, self::isHostname(...)))), 0, self::MAX_HOSTNAMES);
        if ($hostnames === []) {
            Cache::forget(self::CACHE_PREFIX . $organizationId);

            return [];
        }

        $previous = $this->cachedHosts($organizationId);
        $allowPrivate = (bool) config('security.login_policy.dns_allow_private', false);
        $hosts = [];
        foreach ($hostnames as $host) {
            $addresses = array_values(array_unique(array_filter(
                $this->lookup($host),
                static fn (string $address): bool => IPHelper::isValidIP($address) && ($allowPrivate || IPHelper::isPublicIP($address)),
            )));
            if ($addresses !== []) {
                $hosts[$host] = ['addresses' => $addresses, 'resolved_at' => now()->getTimestamp()];
            } elseif (isset($previous[$host])) {
                // Letzter Erfolg bleibt mit seinem Zeitpunkt stehen und läuft nach STALE_SECONDS aus.
                $hosts[$host] = $previous[$host];
            }
        }

        Cache::put(self::CACHE_PREFIX . $organizationId, ['hosts' => $hosts, 'checked_at' => now()->getTimestamp()], self::CACHE_SECONDS);

        return $this->resolvedHosts($organizationId);
    }

    /**
     * Auflösung eines Namens in IPv4- und IPv6-Adressen über `getent ahosts`
     * mit hartem Zeitlimit (PHPs Resolver kennt keins). Ohne `getent` oder bei
     * Zeitüberschreitung: keine Adressen. In Tests ersetzbar.
     *
     * @return list<string>
     */
    protected function lookup(string $host): array {
        try {
            $result = Shell::run(['getent', 'ahosts', $host], self::LOOKUP_TIMEOUT);
        } catch (\Throwable $e) {
            Log::warning('login_policy.dns_lookup_failed', ['host' => $host, 'error' => $e->getMessage()]);

            return [];
        }
        if ($result->timedOut || ! $result->isSuccessful()) {
            return [];
        }

        $addresses = [];
        foreach (preg_split('/\R/', $result->output) ?: [] as $line) {
            $address = strtok(trim($line), " \t");
            if (is_string($address) && IPHelper::isValidIP($address)) {
                $addresses[] = $address;
            }
        }

        return array_values(array_unique($addresses));
    }

    /** @return array<string, array{addresses: list<string>, resolved_at: int}> */
    private function cachedHosts(int $organizationId): array {
        $cached = Cache::get(self::CACHE_PREFIX . $organizationId);
        if (! is_array($cached) || ! is_array($cached['hosts'] ?? null)) {
            return [];
        }

        $hosts = [];
        foreach ($cached['hosts'] as $host => $entry) {
            if (! is_string($host) || ! is_array($entry) || ! is_int($entry['resolved_at'] ?? null) || ! is_array($entry['addresses'] ?? null)) {
                continue;
            }
            $hosts[$host] = [
                'addresses' => array_values(array_filter($entry['addresses'], static fn (mixed $address): bool => is_string($address) && IPHelper::isValidIP($address))),
                'resolved_at' => $entry['resolved_at'],
            ];
        }

        return $hosts;
    }

    private static function matches(string $ip, string $entry): bool {
        if (! str_contains($entry, '/')) {
            if (! IPHelper::isValidIP($entry)) {
                return false;
            }
            $entry .= IPHelper::isIPv6($entry) ? '/128' : '/32';
        }

        return IPHelper::isInRange($ip, $entry);
    }
}
