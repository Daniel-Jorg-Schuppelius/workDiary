<?php
/*
 * Created on   : Mon Nov 09 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : EasybillRetainerPublisher.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Plugins\Easybill\Services\Retainer;

use App\Enums\Finance\BillingMode;
use App\Plugins\Easybill\EasybillPlugin;
use App\Plugins\Easybill\Services\{EasybillDocumentIssuer, EasybillVoucherPullService};
use App\Services\Billing\Retainer\MirroredRetainerPublisher;
use App\Services\Numbering\Contracts\DocumentIssuer;

/** Pauschalen in easybill (MVP-1505): `POST /documents` als Entwurf, dann `PUT /documents/{id}/done`; der Zahlstand kommt aus dem Spiegel. */
final class EasybillRetainerPublisher extends MirroredRetainerPublisher {
    public function pluginId(): string {
        return EasybillPlugin::ID;
    }

    public function label(): string {
        return BillingMode::Easybill->programName();
    }

    protected function configuredFor(int $organizationId): bool {
        return app(EasybillVoucherPullService::class)->isConfigured($organizationId);
    }

    protected function issuer(): DocumentIssuer {
        return app(EasybillDocumentIssuer::class);
    }

    protected function numberPrefix(): string {
        return 'EB';
    }
}
