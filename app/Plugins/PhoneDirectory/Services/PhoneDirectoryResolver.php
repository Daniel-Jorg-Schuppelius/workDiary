<?php
/*
 * Created on   : Mon Sep 29 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : PhoneDirectoryResolver.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Plugins\PhoneDirectory\Services;

use APIToolkit\API\Authentication\BearerAuthentication;
use App\Models\Platform\Organization;
use App\Plugins\PhoneDirectory\{PhoneDirectoryConfig, PhoneDirectoryPlugin};
use App\Plugins\Support\PluginHttpFactory;
use App\Services\Contacts\{ExternalPhoneContact, PhoneNumberResolver};
use CommonToolkit\Enums\HashAlgorithm;
use CommonToolkit\Helper\Data\CryptoHelper;
use Illuminate\Support\Facades\{Cache, Log};

/**
 * Rückwärts-Telefonauskunft über einen oder mehrere vom Betreiber gestellte
 * HTTP-Endpunkte. Jeder Endpunkt bekommt die Rufnummer in E.164
 * (`{number}`-Platzhalter oder Query `number=`) und liefert JSON `{"name": "…"}`;
 * der erste Treffer gewinnt.
 *
 * Datenschutz: Punkt-Abfrage nur im Opt-in ({@see isAvailable()}). Treffer und
 * Nicht-Treffer werden je Organisation zwischengespeichert (Rufnummer nur gehasht
 * im Cache-Schlüssel, nie im Log), damit der Endpunkt nicht bei jedem Abgleich
 * erneut befragt wird.
 */
final class PhoneDirectoryResolver implements PhoneNumberResolver {
    /** Treffer selten wechselnd — lange halten (30 Tage). */
    private const CACHE_TTL_HIT = 60 * 60 * 24 * 30;

    /** Nicht-Treffer kürzer halten (3 Tage), damit neue Einträge nachrücken. */
    private const CACHE_TTL_MISS = 60 * 60 * 24 * 3;

    /** @var array<string, ?string> Lauf-Cache je Organisation+E.164 (aufgelöster Name). */
    private array $memo = [];

    public function id(): string {
        return PhoneDirectoryPlugin::ID;
    }

    public function label(): string {
        return (string) __('Telefonauskunft');
    }

    public function isAvailable(Organization $organization): bool {
        $config = PhoneDirectoryConfig::resolve($organization->id);

        return $config['enabled'] && $config['endpoints'] !== [];
    }

    public function resolve(Organization $organization, string $e164): ?ExternalPhoneContact {
        if ($e164 === '') {
            return null;
        }
        $config = PhoneDirectoryConfig::resolve($organization->id);
        if (! $config['enabled'] || $config['endpoints'] === []) {
            return null;
        }

        $memoKey = $organization->id . ':' . $e164;
        if (! array_key_exists($memoKey, $this->memo)) {
            $cacheKey = 'phonedirectory:' . $organization->id . ':' . CryptoHelper::hash($e164, HashAlgorithm::SHA1);
            $cached = Cache::get($cacheKey);
            if ($cached !== null) {
                $this->memo[$memoKey] = ($cached === '' ? null : (string) $cached);
            } else {
                $name = $this->query($config['endpoints'], $e164);
                Cache::put($cacheKey, $name ?? '', $name !== null ? self::CACHE_TTL_HIT : self::CACHE_TTL_MISS);
                $this->memo[$memoKey] = $name;
            }
        }

        $name = $this->memo[$memoKey];

        return $name !== null ? $this->contact($name) : null;
    }

    private function contact(string $name): ExternalPhoneContact {
        return new ExternalPhoneContact(
            providerId: $this->id(),
            providerLabel: $this->label(),
            externalId: '', // reiner Namens-Hinweis, kein verknüpfbares Ziel
            name: $name,
            company: null,
            phones: [],
        );
    }

    /** @param list<array{url: string, token: string}> $endpoints */
    private function query(array $endpoints, string $e164): ?string {
        foreach ($endpoints as $endpoint) {
            $name = $this->queryOne($endpoint['url'], $endpoint['token'], $e164);
            if ($name !== null) {
                return $name;
            }
        }

        return null;
    }

    private function queryOne(string $url, string $token, string $e164): ?string {
        [$target, $query] = $this->buildRequest($url, $e164);

        try {
            $client = app(PluginHttpFactory::class)->coreClient('phonedirectory', $target);
            if ($token !== '') {
                $client->setAuthentication(new BearerAuthentication($token));
            }
            $response = $client->getResponse($target, $query);
            if (! $response->successful()) {
                return null;
            }

            /** @var array<string, mixed> $body */
            $body = (array) ($response->json() ?? []);
            $name = trim((string) ($body['name'] ?? ''));

            return $name !== '' ? $name : null;
        } catch (\Throwable $e) {
            // Die Auskunft darf den Abgleich nie stören; keine Rufnummer loggen.
            Log::warning('phone directory lookup failed', ['class' => class_basename($e)]);

            return null;
        }
    }

    /**
     * `{number}`-Platzhalter (E.164) ersetzen oder die Nummer als Query
     * `number=` anhängen.
     *
     * @return array{0: string, 1: array<string, string>}
     */
    private function buildRequest(string $url, string $e164): array {
        if (str_contains($url, '{number}')) {
            return [str_replace('{number}', rawurlencode($e164), $url), []];
        }

        return [$url, ['number' => $e164]];
    }
}
