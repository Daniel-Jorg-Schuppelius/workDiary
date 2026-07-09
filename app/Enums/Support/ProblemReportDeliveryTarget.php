<?php
/*
 * Created on   : Wed Jul 08 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ProblemReportDeliveryTarget.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Enums\Support;

/**
 * Versandweg einer Fehlermeldung je Betriebsmodell (Feature 041,
 * MVP-053): SaaS-Inbox (Betreiber-UI), konfigurierte Support-Mail,
 * Webhook oder lokaler Export für On-Premise ohne Außenanbindung.
 */
enum ProblemReportDeliveryTarget: string {
    case SaasInbox = 'saas_inbox';
    case Mail = 'mail';
    case Webhook = 'webhook';
    case LocalExport = 'local_export';

    public function label(): string {
        return __('problemreport.delivery.' . $this->value);
    }
}
