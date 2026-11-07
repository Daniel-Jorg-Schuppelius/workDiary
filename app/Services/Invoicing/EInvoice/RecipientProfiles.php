<?php
/*
 * Created on   : Sat Nov 07 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : RecipientProfiles.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Invoicing\EInvoice;

use App\Enums\Invoicing\{EInvoiceRecipientProfile, ExternalDispatchRoute};
use App\Models\Customer\Customer;
use App\Models\Platform\Organization;
use App\Settings\SettingsRegistry;

/**
 * Empfängerprofil der E-Rechnung je Kunde (Feature 196): Kunde vor
 * Organisationsvorgabe, die Grenzwerte aus config/einvoice-profiles.php.
 */
final class RecipientProfiles {
    public function __construct(private readonly SettingsRegistry $settings) {}

    public function for(?Customer $customer, ?Organization $organization): EInvoiceRecipientProfile {
        if ($customer?->einvoice_recipient_profile !== null) {
            return $customer->einvoice_recipient_profile;
        }

        return EInvoiceRecipientProfile::tryFrom((string) $this->setting('einvoice.recipient_profile_default', $organization))
            ?? EInvoiceRecipientProfile::Standard;
    }

    /** Obergrenze der erzeugten XML in Bytes: Kunde, sonst Profil. */
    public function limitBytes(?Customer $customer, ?Organization $organization): int {
        $megabytes = $customer->einvoice_attachment_limit_mb ?? $this->for($customer, $organization)->attachmentLimitMb();

        return max(1, (int) $megabytes) * 1024 * 1024;
    }

    /**
     * Leistungsnachweis automatisch beilegen (D22): Kunde, sonst Behördenprofil,
     * sonst die Vorgabe der Organisation (ab Werk aus).
     */
    public function attachesActivityReport(?Customer $customer, ?Organization $organization): bool {
        if ($customer?->einvoice_attach_activity_report !== null) {
            return $customer->einvoice_attach_activity_report;
        }
        if ($this->for($customer, $organization)->attachesActivityReport()) {
            return true;
        }

        return (bool) $this->setting('einvoice.attach_activity_report', $organization);
    }

    /** Wer stellt extern ausgestellte Rechnungen zu: Kunde, sonst Vorgabe der Organisation (ab Werk der Anbieter). */
    public function dispatchRoute(?Customer $customer, ?Organization $organization): ExternalDispatchRoute {
        if ($customer?->einvoice_dispatch_route !== null) {
            return $customer->einvoice_dispatch_route;
        }

        return ExternalDispatchRoute::tryFrom((string) $this->setting('einvoice.external_dispatch_route', $organization))
            ?? ExternalDispatchRoute::Provider;
    }

    public function setting(string $key, ?Organization $organization): mixed {
        return $this->settings->effective($key, $organization)->value;
    }
}
