<?php
/*
 * Created on   : Sat Nov 07 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : EInvoiceRecipientProfile.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Enums\Invoicing;

use App\Enums\Concerns\HasOptions;
use App\Enums\Contracts\HasLabel;

/**
 * Empfängerprofil der E-Rechnung (Feature 196): Behörden lesen nur die XML,
 * verlangen eingebettete Anlagen und genau eine Datei je Mail. Die Werte je
 * Profil stehen in config/einvoice-profiles.php.
 */
enum EInvoiceRecipientProfile: string implements HasLabel {
    use HasOptions;

    case Standard = 'standard';
    case PublicSector = 'public_sector';

    public function label(): string {
        return match ($this) {
            self::Standard => (string) __('enums.invoicing.einvoice_recipient_profile.standard'),
            self::PublicSector => (string) __('enums.invoicing.einvoice_recipient_profile.public_sector'),
        };
    }

    /** Obergrenze der erzeugten XML in MB (Base64 eingerechnet). */
    public function attachmentLimitMb(): int {
        return max(1, (int) config('einvoice-profiles.' . $this->value . '.attachment_limit_mb', 10));
    }

    /** Nur die XRechnungs-Datei je Mail, kein PDF daneben. */
    public function xmlOnlyMail(): bool {
        return (bool) config('einvoice-profiles.' . $this->value . '.xml_only_mail', false);
    }

    /** Käuferreferenz (BT-10, Leitweg-ID) ist Pflicht statt Warnung. */
    public function buyerReferenceRequired(): bool {
        return (bool) config('einvoice-profiles.' . $this->value . '.buyer_reference_required', false);
    }

    /** Leistungsnachweis ohne eigene Vorgabe von Kunde oder Organisation beilegen. */
    public function attachesActivityReport(): bool {
        return (bool) config('einvoice-profiles.' . $this->value . '.attach_activity_report', false);
    }
}
