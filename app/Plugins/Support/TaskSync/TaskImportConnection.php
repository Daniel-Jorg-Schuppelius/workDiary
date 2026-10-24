<?php
/*
 * Created on   : Fri Oct 23 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : TaskImportConnection.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Plugins\Support\TaskSync;

/** Verbindung eines Aufgabenimports, umgesetzt über {@see IsTaskImportConnection}. */
interface TaskImportConnection {
    public function isReady(): bool;

    public function sourceId(): ?string;

    /** @return list<array{id: string, name: string, url: ?string}> */
    public function availableSources(): array;

    /** @param array{created: int, updated: int, unchanged: int, conflicts: int, inbox: int, failed: int} $counters */
    public function markSynced(array $counters): void;

    public function recordConnectionFailure(string $error): void;

    public function recordConnectionSuccess(): void;
}
