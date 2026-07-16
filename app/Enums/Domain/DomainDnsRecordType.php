<?php
/*
 * Created on   : Thu Jul 16 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : DomainDnsRecordType.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Enums\Domain;

use App\Enums\Concerns\HasOptions;
use App\Enums\Contracts\HasLabel;

/**
 * Unterstützte DNS-Resource-Record-Typen (Feature 083, MVP-389). Die Oberfläche
 * nimmt nur typisierte Records entgegen; {@see \App\Services\Domain\DomainDnsService}
 * serialisiert sie anschließend in das vom Provider erwartete RR-Format — es
 * werden keine rohen Befehlszeilen akzeptiert.
 */
enum DomainDnsRecordType: string implements HasLabel {
    use HasOptions;

    case A = 'A';
    case AAAA = 'AAAA';
    case CNAME = 'CNAME';
    case MX = 'MX';
    case TXT = 'TXT';
    case NS = 'NS';
    case SRV = 'SRV';
    case CAA = 'CAA';
    case PTR = 'PTR';
    case ALIAS = 'ALIAS';

    public function label(): string {
        return $this->value;
    }

    /** Records mit vorangestellter Priorität (MX/SRV). */
    public function hasPriority(): bool {
        return $this === self::MX || $this === self::SRV;
    }
}
