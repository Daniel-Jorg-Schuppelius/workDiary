<?php
/*
 * Created on   : Thu Jul 16 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : DomainCapabilityArea.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Enums\Domain;

use App\Enums\Concerns\HasOptions;
use App\Enums\Contracts\HasLabel;

/**
 * Fähigkeitsbereiche des DomainReselling-Adapters (Feature 083). Die
 * {@see \App\Plugins\Support\Domain\DomainCapabilityMatrix} führt je Bereich,
 * ob er im realen Konto belegt ist. `Invoices` ist im dokumentierten Vertrag
 * NICHT belegt und bleibt bis zu einem Capability-Pilot gesperrt (MVP-393);
 * gesperrte Bereiche erscheinen als erklärter Blocked-State, nicht als Button.
 */
enum DomainCapabilityArea: string implements HasLabel {
    use HasOptions;

    case Authentication = 'authentication';
    case Subuser = 'subuser';
    case Domains = 'domains';
    case Contacts = 'contacts';
    case Nameservers = 'nameservers';
    case Dns = 'dns';
    case Events = 'events';
    case Renewal = 'renewal';
    case Transfer = 'transfer';
    case Accounting = 'accounting';
    case Invoices = 'invoices';

    public function label(): string {
        return (string) __('enums.domain.capability_area.' . $this->value);
    }

    /**
     * Ob der Bereich im dokumentierten Handbuch belegt ist. `Invoices` ist die
     * einzige bewusst nicht belegte Fähigkeit — Default-Sperre, bis ein realer
     * Vertrag Rechnungsliste/-PDF eindeutig nachweist.
     */
    public function isDocumented(): bool {
        return $this !== self::Invoices;
    }
}
