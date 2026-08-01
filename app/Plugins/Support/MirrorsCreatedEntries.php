<?php
/*
 * Created on   : Fri Aug 01 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : MirrorsCreatedEntries.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Plugins\Support;

/**
 * Opt-in-Vertrag (MVP-463): Outbox-Dispatcher, die NEU angelegte lokale
 * Zeiteinträge zeitnah ins Fremdsystem spiegeln wollen — zusätzlich zum
 * stündlichen Batch-Push, der als Backfill unverändert bestehen bleibt.
 * {@see TimeWritebackObserver} enqueued dafür je aktiviertem Dispatcher
 * einen Create-Outbox-Eintrag (Idempotenzschlüssel `<plugin>-entry-create:<id>`).
 */
interface MirrorsCreatedEntries {
    public function pluginId(): string;

    /** Ist der Spiegel-Export (Create-Pfad) für diese Organisation aktiv? */
    public function mirrorCreateEnabled(int $organizationId): bool;

    /** Outbox-Operation des Create-Pfads (z. B. `toggl.entry.create`). */
    public function createOperation(): string;
}
