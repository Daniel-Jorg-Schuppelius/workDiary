<?php
/*
 * Created on   : Thu Sep 24 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : SyncCommandHandler.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Sync\Contracts;

use App\Models\Platform\User;

/**
 * Erweiterungspunkt der Offline-Sync-Outbox (MVP-863): Ein Modul führt seine
 * Befehlstypen selbst aus und meldet den Handler über `Manifest::extensions()`.
 * Ablehnung per ValidationException/RuntimeException, Konflikt per
 * {@see \App\Services\Sync\SyncConflictException}; die Rahmenlogik
 * (Idempotenz, Audit, Antwort) bleibt im {@see \App\Services\Sync\SyncCommandService}.
 */
interface SyncCommandHandler {
    /** @return list<string> Befehlstypen (`bereich.aktion`) */
    public function types(): array;

    /**
     * @param  array<string, mixed>  $payload
     * @return string Ergebnis-Referenz `<tabelle>:<id>`
     */
    public function handle(User $user, string $type, array $payload): string;
}
