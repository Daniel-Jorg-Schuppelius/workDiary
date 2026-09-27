<?php
/*
 * Created on   : Sun Sep 27 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ContractSigningCompleted.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Events\Contract;

use App\Models\Contract\ContractSigningRevision;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;

/** Fassung vollständig unterzeichnet (MVP-939) — Folgeprozesse wie die Ablage in der Personalakte. */
final class ContractSigningCompleted implements ShouldDispatchAfterCommit {
    use Dispatchable;

    public function __construct(public readonly ContractSigningRevision $revision) {}
}
