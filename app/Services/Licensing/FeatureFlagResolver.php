<?php
/*
 * Created on   : Sun May 24 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : FeatureFlagResolver.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Services\Licensing;

/**
 * Löst Feature-Flags auf (MVP-047 §4 / Folge zu MVP-047).
 *
 * Auflösungsreihenfolge (vereinfacht für diese Iteration):
 *  1. Env-Override `config('license.feature_overrides')` (assoc code → bool)
 *  2. Lizenz-Payload (`LicensePayload->features` als list<string>; enthalten = on)
 *  3. Default: off
 *
 * Pro-Organisation-Overrides (`orgOverride`) sind in der Roadmap als
 * `feature_flags`-Tabelle vorgesehen und folgen als separater Schritt.
 * Request-Level-Caching reicht für den MVP — ein 60-s-Cache (`Cache::remember`)
 * lohnt sich erst, sobald die DB-Override-Quelle dazukommt.
 */
class FeatureFlagResolver {
    /** @var array<string, bool>|null */
    private ?array $resolved = null;

    public function __construct(private readonly LicenseService $licenses) {}

    public function isEnabled(string $code): bool {
        $map = $this->resolve();

        return $map[$code] ?? false;
    }

    /** @return array<string, bool> */
    public function all(): array {
        return $this->resolve();
    }

    public function flush(): void {
        $this->resolved = null;
    }

    /** @return array<string, bool> */
    private function resolve(): array {
        if ($this->resolved !== null) {
            return $this->resolved;
        }

        $map = [];

        $licenseResult = $this->licenses->current();
        $payload = $licenseResult->payload;
        if ($payload !== null && $licenseResult->isUsable()) {
            foreach ($payload->features as $code) {
                $code = (string) $code;
                if ($code !== '') {
                    $map[$code] = true;
                }
            }
        }

        /** @var array<string, bool> $envOverrides */
        $envOverrides = (array) config('license.feature_overrides', []);
        foreach ($envOverrides as $code => $enabled) {
            if (! is_string($code) || $code === '') {
                continue;
            }
            $map[$code] = (bool) $enabled;
        }

        return $this->resolved = $map;
    }
}
