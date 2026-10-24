<?php
/*
 * Created on   : Sat Oct 24 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : IsShopConnection.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Plugins\Support\ShopOrders;

use App\Plugins\Support\OAuthConnectionStatus;

/**
 * Verbindung eines Shop-Imports: die Instanz hält „Verbindung prüfen“ fest
 * (`source_id` aus {@see \App\Plugins\Support\TaskSync\SelfHostedInstance}),
 * Bestellungen kommen erst nach „Import bestätigen“. Die Zugangsdaten stehen
 * in den Plugin-Einstellungen. Erwartet {@see \App\Models\Concerns\HasConnectionHealth}.
 */
trait IsShopConnection {
    public function initializeIsShopConnection(): void {
        $this->mergeCasts([
            'status' => OAuthConnectionStatus::class,
            'connected_at' => 'datetime',
            'disconnected_at' => 'datetime',
            'import_confirmed_at' => 'datetime',
            'checkpoint_at' => 'immutable_datetime',
            'last_full_sync_at' => 'immutable_datetime',
            'last_synced_at' => 'immutable_datetime',
            'last_sync_counters' => 'array',
            'is_last_run_complete' => 'boolean',
        ]);
    }

    /** Geprüft und nicht automatisch abgeschaltet. */
    public function isReady(): bool {
        return $this->getAttribute('status') === OAuthConnectionStatus::Active
            && $this->sourceId() !== null
            && $this->getAttribute('disabled_at') === null;
    }

    public function isImportConfirmed(): bool {
        return $this->getAttribute('import_confirmed_at') !== null;
    }

    public function sourceId(): ?string {
        $value = trim((string) $this->getAttribute('source_id'));

        return $value !== '' ? $value : null;
    }
}
