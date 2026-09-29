<?php
/*
 * Created on   : Wed Aug 26 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : VoucherPullerRegistry.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Finance\Accounting\Vouchers;

/**
 * Alle Beleg-Puller (Feature 122, MVP-731). Die Anbindungen tragen sich beim
 * Booten ein (MVP-1031) — auch InvoicePlane, das mangels API
 * keine Plugin-Klasse hat, über seinen eigenen ServiceProvider.
 */
class VoucherPullerRegistry {
    /** @var list<class-string<VoucherPuller>> */
    private array $classes = [];

    /** @var list<VoucherPuller>|null */
    private ?array $pullers = null;

    /** @param  class-string<VoucherPuller>  $puller */
    public function register(string $puller): void {
        $this->classes[] = $puller;
        $this->pullers = null;
    }

    /** @return list<VoucherPuller> in Eintragsreihenfolge */
    public function all(): array {
        return $this->pullers ??= array_map(static fn (string $class): VoucherPuller => app($class), $this->classes);
    }

    public function find(string $pluginId): ?VoucherPuller {
        foreach ($this->all() as $puller) {
            if ($puller->pluginId() === $pluginId) {
                return $puller;
            }
        }

        return null;
    }

    /** @return list<string> */
    public function pluginIds(): array {
        return array_map(static fn (VoucherPuller $p): string => $p->pluginId(), $this->all());
    }

    /**
     * Puller, die für diese Organisation tatsächlich eingerichtet sind.
     *
     * @return list<VoucherPuller>
     */
    public function configuredFor(int $organizationId): array {
        return array_values(array_filter(
            $this->all(),
            static fn (VoucherPuller $p): bool => $p->isConfigured($organizationId),
        ));
    }
}
