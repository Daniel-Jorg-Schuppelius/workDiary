<?php
/*
 * Created on   : Fri Oct 23 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : IsTaskImportConnection.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Plugins\Support\TaskSync;

use App\Plugins\Support\OAuthConnectionStatus;
use Carbon\CarbonImmutable;

/**
 * Verbindung eines Aufgabenimports, genau eine je Organisation: OAuth
 * (Jira-Site, Asana-Workspace; die Quelle wird nach dem Consent aus
 * `available_sources` gewählt) oder Token (Gitea/Forgejo, Redmine; die Instanz
 * hält „Verbindung prüfen“ fest). Erwartet die gemeinsamen Spalten der
 * Migrationen und {@see \App\Models\Concerns\HasConnectionHealth}.
 */
trait IsTaskImportConnection {
    public function initializeIsTaskImportConnection(): void {
        // Token-Verbindungen haben keine Token-Spalten; ein Cast darauf ließe den Rekey-Lauf an ihnen scheitern.
        if ($this->storesTokens()) {
            $this->mergeCasts([
                'access_token' => 'encrypted',
                'refresh_token' => 'encrypted',
                'token_expires_at' => 'datetime',
            ]);
        }
        $this->mergeCasts([
            'status' => OAuthConnectionStatus::class,
            'available_sources' => 'array',
            'last_synced_at' => 'immutable_datetime',
            'last_sync_counters' => 'array',
            'connected_at' => 'datetime',
            'disconnected_at' => 'datetime',
        ]);
    }

    /** Verbunden, Zugang vorhanden, Quelle gewählt und nicht automatisch abgeschaltet. */
    public function isReady(): bool {
        return $this->getAttribute('status') === OAuthConnectionStatus::Active
            && $this->hasCredentials()
            && trim((string) $this->getAttribute('source_id')) !== ''
            && $this->getAttribute('disabled_at') === null;
    }

    /** OAuth: Token an der Verbindung. Token-Plugins halten den Zugang in den Plugin-Einstellungen und geben false zurück. */
    protected function storesTokens(): bool {
        return true;
    }

    private function hasCredentials(): bool {
        return ! $this->storesTokens() || trim((string) $this->getAttribute('access_token')) !== '';
    }

    public function sourceId(): ?string {
        $value = trim((string) $this->getAttribute('source_id'));

        return $value !== '' ? $value : null;
    }

    /**
     * Nach dem Consent erreichbare Quellen (Site bzw. Workspace).
     *
     * @return list<array{id: string, name: string, url: ?string}>
     */
    public function availableSources(): array {
        $sources = [];
        foreach ((array) $this->getAttribute('available_sources') as $source) {
            if (is_array($source) && is_string($source['id'] ?? null) && $source['id'] !== '') {
                $sources[] = [
                    'id' => $source['id'],
                    'name' => is_string($source['name'] ?? null) ? $source['name'] : $source['id'],
                    'url' => is_string($source['url'] ?? null) ? $source['url'] : null,
                ];
            }
        }

        return $sources;
    }

    /** @param array{created: int, updated: int, unchanged: int, conflicts: int, inbox: int, failed: int} $counters */
    public function markSynced(array $counters): void {
        $this->forceFill([
            'last_synced_at' => CarbonImmutable::now(),
            'last_sync_counters' => $counters,
        ])->save();
    }
}
