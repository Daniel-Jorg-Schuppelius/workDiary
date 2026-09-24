<?php
/*
 * Created on   : Thu Sep 24 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ContractObligationSink.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Reselling\Contracts;

use App\Models\Contract\{Contract, ContractObligation};

/**
 * Pflichten aus Abo-Perioden am Vertrag (MVP-863): definiert vom Reselling,
 * gebunden vom Vertragsmodul ({@see \App\Services\Contract\ContractService}).
 * Null-Bindung: keine Pflicht angelegt (kein Vertragsmodul, keine Termine).
 */
interface ContractObligationSink {
    /** @param array<string, mixed> $attributes */
    public function addObligation(Contract $contract, array $attributes): ?ContractObligation;
}
