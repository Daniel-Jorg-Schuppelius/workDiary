<?php
/*
 * Created on   : Fri Jul 10 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ClaimSource.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Enums\Claims;

/** Eingangskanal einer Reklamation (MVP-248). */
enum ClaimSource: string {
    case Portal = 'portal';
    case Email = 'email';
    case Phone = 'phone';
    case Helpdesk = 'helpdesk';
    case Order = 'order';
    case Protocol = 'protocol';
    case Asset = 'asset';
    case Invoice = 'invoice';
    case Api = 'api';
    case Internal = 'internal';
    case Manual = 'manual';

    public function label(): string {
        return match ($this) {
            self::Portal => (string) __('Kundenportal'),
            self::Email => (string) __('E-Mail'),
            self::Phone => (string) __('Telefonnotiz'),
            self::Helpdesk => (string) __('Helpdesk'),
            self::Order => (string) __('Auftrag'),
            self::Protocol => (string) __('Abnahmeprotokoll'),
            self::Asset => (string) __('Asset'),
            self::Invoice => (string) __('Rechnung'),
            self::Api => (string) __('API'),
            self::Internal => (string) __('Interner Mangel'),
            self::Manual => (string) __('Manuell'),
        };
    }
}
