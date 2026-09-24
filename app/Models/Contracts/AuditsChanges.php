<?php
/*
 * Created on   : Thu Sep 24 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : AuditsChanges.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Models\Contracts;

use App\Models\Audit\AuditLog;

/** Vertrag des {@see \App\Models\Concerns\Auditable}-Traits für Dienste, die ein Modell nur generisch kennen (MVP-863). */
interface AuditsChanges {
    /** @param array<string, mixed> $changes */
    public function audit(string $event, array $changes = []): AuditLog;
}
