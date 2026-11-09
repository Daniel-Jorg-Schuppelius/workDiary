<?php
/*
 * Created on   : Mon Nov 09 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : SevDeskRetainerPublisher.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Plugins\SevDesk\Services\Retainer;

use App\Enums\Finance\BillingMode;
use App\Plugins\SevDesk\Services\{SevDeskDocumentIssuer, SevDeskVoucherPullService};
use App\Plugins\SevDesk\SevDeskPlugin;
use App\Services\Billing\Retainer\MirroredRetainerPublisher;
use App\Services\Numbering\Contracts\DocumentIssuer;

/** Pauschalen in sevDesk (MVP-1505): `saveInvoice` als Entwurf, dann `sendBy`; der Zahlstand kommt aus dem Spiegel. */
final class SevDeskRetainerPublisher extends MirroredRetainerPublisher {
    public function pluginId(): string {
        return SevDeskPlugin::ID;
    }

    public function label(): string {
        return BillingMode::SevDesk->programName();
    }

    protected function configuredFor(int $organizationId): bool {
        return app(SevDeskVoucherPullService::class)->isConfigured($organizationId);
    }

    protected function issuer(): DocumentIssuer {
        return app(SevDeskDocumentIssuer::class);
    }

    protected function numberPrefix(): string {
        return 'SD';
    }
}
