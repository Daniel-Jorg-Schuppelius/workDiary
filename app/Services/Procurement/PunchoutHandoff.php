<?php
/*
 * Created on   : Fri Oct 02 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : PunchoutHandoff.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Procurement;

use App\Models\Inventory\Warehouse;
use App\Models\Platform\User;
use App\Models\Supplier\SupplierCatalogSource;
use CommonToolkit\Helper\Data\CryptoHelper;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/**
 * Übergaben rund um den Shop-Rücksprung (MVP-096, MVP-1071).
 *
 * Hook: IDS-Connect erlaubt für die Rücksprungadresse höchstens 256 Zeichen —
 * eine signierte Route mit Quelle, Lager, Nutzer und Signatur sprengt das je
 * nach Domain. Die Adresse trägt deshalb nur ein Einmal-Token, der Kontext
 * liegt serverseitig.
 *
 * Ergebnis: Der Rücksprung läuft sitzungslos, sonst ersetzte das neue
 * Sitzungscookie der Antwort die Anmeldung des Einkäufers. Seine Meldungen
 * holt eine angemeldete Folgeadresse ab und zeigt sie dem Nutzer an, für den
 * der Absprung erzeugt wurde.
 */
final class PunchoutHandoff {
    private const HOOK_PREFIX = 'procurement.punchout-hook:';

    private const RESULT_PREFIX = 'procurement.punchout-result:';

    private const HOOK_TTL_HOURS = 2;

    private const RESULT_TTL_MINUTES = 15;

    public function issueHook(SupplierCatalogSource $source, Warehouse $warehouse, User $user): string {
        $token = Str::random(40);
        Cache::put(self::HOOK_PREFIX . CryptoHelper::hash($token), [
            'source' => (int) $source->id,
            'warehouse' => (int) $warehouse->id,
            'user' => (int) $user->id,
        ], now()->addHours(self::HOOK_TTL_HOURS));

        return $token;
    }

    /** @return array{source: int, warehouse: int, user: int}|null */
    public function consumeHook(string $token): ?array {
        $context = Cache::pull(self::HOOK_PREFIX . CryptoHelper::hash($token));
        if (! is_array($context)) {
            return null;
        }

        return ['source' => (int) $context['source'], 'warehouse' => (int) $context['warehouse'], 'user' => (int) $context['user']];
    }

    /**
     * @param  array<string, string>  $flash  Schlüssel success|warning|error
     * @return string Schlüssel für {@see pullResult()}
     */
    public function stashResult(int $userId, string $target, array $flash): string {
        $key = Str::random(40);
        Cache::put(self::RESULT_PREFIX . CryptoHelper::hash($key), [
            'user' => $userId,
            'target' => $target,
            'flash' => $flash,
        ], now()->addMinutes(self::RESULT_TTL_MINUTES));

        return $key;
    }

    /** @return array{target: string, flash: array<string, string>}|null */
    public function pullResult(string $key, int $userId): ?array {
        $result = Cache::pull(self::RESULT_PREFIX . CryptoHelper::hash($key));
        if (! is_array($result) || (int) $result['user'] !== $userId) {
            return null;
        }

        return ['target' => (string) $result['target'], 'flash' => (array) $result['flash']];
    }
}
