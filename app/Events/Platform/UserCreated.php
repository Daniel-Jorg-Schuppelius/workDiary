<?php
/*
 * Created on   : Thu Oct 22 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : UserCreated.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Events\Platform;

use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Mitarbeitende Person angelegt (MVP-1273), nach dem Commit — Folgeprozesse
 * wie Automationsregeln sehen Rolle und Stammdaten der anlegenden Transaktion.
 */
final class UserCreated implements ShouldDispatchAfterCommit {
    use Dispatchable;

    public function __construct(
        public readonly int $organizationId,
        public readonly int $userId,
    ) {}
}
