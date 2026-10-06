<?php
/*
 * Created on   : Tue Sep 29 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ProvidesRemoteSessions.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Plugins\Contracts;

/** Plugin liefert Fernwartungssitzungen (`remote_pending_sessions`); die Suche zeigt sie nur bei aktivem Lieferanten (MVP-1044). */
interface ProvidesRemoteSessions {
    /** Ziel eines Suchtreffers auf eine Sitzung (MVP-1046). */
    public function remoteSessionsUrl(): string;

    /**
     * Zeiteinträge aus Sitzungen je Asset, für die Wartungszeit der Asset-Auswertung.
     *
     * @param  list<int>  $assetIds
     * @return array<int, int> Zeiteintrag-ID → Asset-ID
     */
    public function remoteSessionAssets(array $assetIds): array;

    /**
     * Letzte importierte Sitzungen der Organisation — reine Historie für die
     * Sitzungsübersicht.
     *
     * @return list<array{provider: string, label: string, started_at: mixed, ended_at: mixed, status: string}>
     */
    public function recentRemoteSessions(int $organizationId, int $limit): array;
}
