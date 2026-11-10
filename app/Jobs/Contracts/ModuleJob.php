<?php
/*
 * Created on   : Mon Nov 09 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ModuleJob.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Jobs\Contracts;

/**
 * Queue-Job eines Fachmoduls (MVP-1518): {@see \App\Jobs\Middleware\EnsureModuleActive}
 * führt ihn nur aus, wenn das Modul für seine Organisation aktiv ist. Das Modul
 * ergibt sich aus dem Domänenordner (`App\Jobs\<Ordner>\…`), außerhalb eines
 * Ordners aus {@see \App\Modules\Attributes\BelongsToModule}.
 */
interface ModuleJob {
    /** Organisation, für die der Job wirkt; null = ohne Organisationsbezug (läuft ungeprüft). */
    public function organizationId(): ?int;
}
