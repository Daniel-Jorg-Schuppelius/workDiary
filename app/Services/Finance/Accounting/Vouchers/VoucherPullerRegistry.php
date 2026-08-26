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

use App\Plugins\Easybill\Services\EasybillVoucherPullService;
use App\Plugins\InvoicePlane\Services\InvoicePlaneVoucherPullService;
use App\Plugins\JtlWawi\Services\JtlVoucherPullService;
use App\Plugins\SevDesk\Services\SevDeskVoucherPullService;

/**
 * Alle Beleg-Puller in fester Reihenfolge (Feature 122, MVP-731).
 *
 * Muster der {@see \App\Services\Finance\Targets\FacturationTargetRegistry}:
 * Die Anbindungen hängen an Registries, nicht an Plugin-Capabilities — so
 * kann auch InvoicePlane mitspielen, das (mangels API) gar keine Plugin-
 * Klasse hat.
 */
class VoucherPullerRegistry {
    /** @var list<VoucherPuller> */
    private readonly array $pullers;

    public function __construct(
        SevDeskVoucherPullService $sevdesk,
        EasybillVoucherPullService $easybill,
        InvoicePlaneVoucherPullService $invoiceplane,
        JtlVoucherPullService $jtl,
    ) {
        $this->pullers = [$sevdesk, $easybill, $invoiceplane, $jtl];
    }

    /** @return list<VoucherPuller> */
    public function all(): array {
        return $this->pullers;
    }

    public function find(string $pluginId): ?VoucherPuller {
        foreach ($this->pullers as $puller) {
            if ($puller->pluginId() === $pluginId) {
                return $puller;
            }
        }

        return null;
    }

    /** @return list<string> */
    public function pluginIds(): array {
        return array_map(static fn (VoucherPuller $p): string => $p->pluginId(), $this->pullers);
    }

    /**
     * Puller, die für diese Organisation tatsächlich eingerichtet sind.
     *
     * @return list<VoucherPuller>
     */
    public function configuredFor(int $organizationId): array {
        return array_values(array_filter(
            $this->pullers,
            static fn (VoucherPuller $p): bool => $p->isConfigured($organizationId),
        ));
    }
}
