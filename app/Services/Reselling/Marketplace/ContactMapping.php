<?php
/*
 * Created on   : Thu Sep 03 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ContactMapping.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Reselling\Marketplace;

use App\Models\Customer;

/**
 * Zuordnung einer Marketplace-Firma zu Kunde und Fremdsystem-Kontakt(en).
 * `source` sagt, woher die Zuordnung stammt, damit der Bericht prüfbar bleibt.
 */
final readonly class ContactMapping {
    public const SOURCE_MANUAL = 'Zuordnungsdatei';

    public const SOURCE_STORED = 'Zuordnung (gespeichert)';

    public const SOURCE_REFERENCE = 'Kunde + Verknüpfung';

    public const SOURCE_NUMBER = 'Kundennummer';

    public const SOURCE_PARTNER_NUMBER = 'Partner-Kundennummer';

    public const SOURCE_SEARCH = 'Namenssuche';

    public const SOURCE_FOREIGN = 'Fremdkunde';

    public const SOURCE_NONE = '—';

    /**
     * @param  list<string>  $contactIds
     * @param  list<string>  $candidates  Klartext-Kandidaten für die manuelle Nacharbeit
     * @param  string  $detail  Grund der Zuordnung (Matching-Signal, Namensabweichung) — zum Prüfen, nicht zum Vertrauen
     * @param  string|null  $billedVia  Name des Partners, der die Rechnung bekommt und an den Endkunden weiterreicht (Fremdkunde)
     */
    public function __construct(
        public MarketplaceCompany $company,
        public ?Customer $customer,
        public array $contactIds,
        public string $source,
        public array $candidates = [],
        public string $detail = '',
        public ?string $billedVia = null,
    ) {}

    public function isBilledViaPartner(): bool {
        return $this->billedVia !== null && $this->billedVia !== '';
    }

    public function sourceLabel(): string {
        return $this->detail === '' ? $this->source : $this->source . ' (' . $this->detail . ')';
    }

    public function isResolved(): bool {
        return $this->contactIds !== [];
    }
}
