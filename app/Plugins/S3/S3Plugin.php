<?php
/*
 * Created on   : Tue Sep 01 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : S3Plugin.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Plugins\S3;

use App\Enums\Backup\BackupProvider;
use App\Models\Backup\BackupTargetConnection;
use App\Plugins\{AbstractPlugin, PluginHealth};
use App\Plugins\Contracts\{BackupTarget, PluginCapability};
use App\Plugins\S3\Api\S3BackupClient;
use App\Plugins\Support\Backup\{BackupAccount, BackupRemoteObject};
use Psr\Http\Message\StreamInterface;
use Throwable;

/**
 * S3-kompatibler Objektspeicher als Backupziel (Feature 123, MVP-726).
 *
 * **Nur Backupziel** — kein Dokumentspiegel, kein Import. Das unterscheidet
 * dieses Plugin von WebDAV/Nextcloud, die beides können: Objektspeicher ist
 * für Archive gedacht, nicht für eine Ablage, in die jemand hineinsieht.
 *
 * Die Verbindungen sind **systemweit** (Plattform-Admin) wie bei allen
 * Backupzielen; es gibt bewusst keine Konfiguration je Organisation.
 */
class S3Plugin extends AbstractPlugin implements BackupTarget {
    public const ID = 's3';

    public const SERVICE_PROVIDER = S3ServiceProvider::class;

    public function name(): string {
        return 'S3';
    }

    public function version(): string {
        return '0.1.0';
    }

    public function description(): string {
        return __('Verschlüsselte Offsite-Backups in einen S3-kompatiblen Objektspeicher (AWS S3, MinIO, Wasabi, Hetzner).');
    }

    /**
     * Reines Backupziel — die Capability ist hier Selbstauskunft, kein
     * Verhalten: der Backup-Lauf löst den Adapter über
     * {@see \App\Enums\Backup\BackupProvider::pluginId()} auf.
     */
    public function capabilities(): array {
        return [PluginCapability::BackupTarget];
    }

    /** Die Verwaltung liegt in der Backupziel-Übersicht, nicht in einem eigenen Panel. */
    public function adminPanel(): ?array {
        return null;
    }

    public function settingsSchema(): array {
        return [];
    }

    public function backupAccount(BackupTargetConnection $connection): BackupAccount {
        return $this->backupClient($connection)->account();
    }

    /** @return array{total: int|null, used: int|null} */
    public function backupQuota(BackupTargetConnection $connection): array {
        return $this->backupClient($connection)->quota();
    }

    public function backupEnsureFolder(BackupTargetConnection $connection, string $path): string {
        return $this->backupClient($connection)->ensureFolder($path);
    }

    /** @return list<BackupRemoteObject> */
    public function backupList(BackupTargetConnection $connection, string $prefix): array {
        return $this->backupClient($connection)->listObjects($prefix);
    }

    public function backupUploadPart(BackupTargetConnection $connection, string $localPath, string $remoteName): string {
        return $this->backupClient($connection)->upload($localPath, $remoteName);
    }

    public function backupDownload(BackupTargetConnection $connection, string $remoteRef): StreamInterface {
        return $this->backupClient($connection)->download($remoteRef);
    }

    public function backupDelete(BackupTargetConnection $connection, string $remoteRef): bool {
        return $this->backupClient($connection)->delete($remoteRef);
    }

    /** Tests binden hierüber einen Client mit gemocktem SDK-Handler. */
    public function backupClient(BackupTargetConnection $connection): S3BackupClient {
        return app()->makeWith(S3BackupClient::class, ['connection' => $connection]);
    }

    /**
     * Systemweiter Health-Check: die eingerichteten S3-Ziele anpingen.
     *
     * Anders als bei den org-gebundenen Plugins gibt es hier keinen
     * Org-Kontext — Backupziele gehören der Installation.
     */
    public function healthCheck(): PluginHealth {
        $connections = BackupTargetConnection::query()
            ->where('provider', BackupProvider::S3->value)
            ->get();

        if ($connections->isEmpty()) {
            return PluginHealth::degraded(__('Kein S3-Backupziel eingerichtet.'));
        }

        foreach ($connections as $connection) {
            if (! $connection->isRunnable()) {
                continue;
            }

            try {
                $this->backupClient($connection)->ensureFolder((string) $connection->root_folder_ref);
            } catch (Throwable $e) {
                // Nie die Meldung selbst: sie trägt Endpoint und Signaturkopf.
                return PluginHealth::failing(
                    __('S3-Ziel ":name" nicht erreichbar (:class).', [
                        'name' => (string) $connection->name,
                        'class' => class_basename($e),
                    ]),
                    'unreachable',
                );
            }
        }

        return PluginHealth::ok(__(':count S3-Ziel(e) erreichbar.', ['count' => $connections->count()]));
    }
}
