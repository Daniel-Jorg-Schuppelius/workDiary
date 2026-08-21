<?php
/*
 * Created on   : Sun Jul 05 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : WebdavPlugin.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Plugins\Webdav;

use App\Models\Backup\BackupTargetConnection;
use App\Models\WebdavConnection;
use App\Plugins\{AbstractPlugin, PluginHealth};
use App\Plugins\Contracts\{BackupTarget, Plugin};
use App\Plugins\Support\Backup\{BackupAccount, BackupRemoteObject};
use App\Plugins\Webdav\Api\WebdavBackupClient;
use App\Plugins\Webdav\Contracts\WebdavGatewayFactory;
use Psr\Http\Message\StreamInterface;
use Throwable;

/**
 * On-Premise-Dokumentablage über WebDAV (Feature 058, MVP-127).
 *
 * - **Spiegelt** freigegebene DMS-Dokumente ({@see \App\Models\Document},
 *   Status `Active`) in eine externe WebDAV-Ablage (Nextcloud/ownCloud,
 *   generisch) — nach Regel Dokumenttyp→Zielordner, mit Übergabenachweis
 *   (SHA-256 + Zeit + Ziel) in {@see \App\Models\ExternalReference}.
 * - WorkDiary bleibt führend und revisionssicher; **kein Rückkanal**. Externe
 *   Änderungen an gespiegelten Dateien führen zu sichtbaren Konflikten
 *   ({@see \App\Models\IntegrationInboxItem}), nie zu stiller Übernahme.
 * - Zustellung/Retry/Idempotenz über die generische Integrations-Outbox
 *   (Feature 055) via {@see Services\WebdavOutboxDispatcher}.
 *
 * Bewusst ohne Sync-Capability: die Spiegelung ist ereignisgetrieben
 * (Freigabe → Outbox), kein providerneutraler Abgleicheinstieg.
 */
class WebdavPlugin extends AbstractPlugin implements BackupTarget {
    public const ID = 'webdav';

    public const SERVICE_PROVIDER = WebdavServiceProvider::class;

    public function name(): string {
        return 'WebDAV';
    }

    public function version(): string {
        return '0.1.0';
    }

    public function description(): string {
        return __('Spiegelt freigegebene Dokumente in eine externe WebDAV-Ablage (Nextcloud/ownCloud) — mit Übergabenachweis und Konfliktanzeige, ohne Rückkanal.');
    }

    /** Ereignisgetriebenes Sink-Plugin ohne providerneutrale Sync-Capability. */
    /**
     * Bewusst leer: Der Dokumentspiegel ist ein
     * {@see \App\Plugins\Support\Mirror\MirrorTarget} und wird über den
     * {@see \App\Plugins\Support\Mirror\DocumentMirrorService} geführt
     * (Audit 2026-08, W1.6).
     */
    public function capabilities(): array {
        return [];
    }

    public function adminPanel(): ?array {
        return [
            'route' => 'admin.webdav.index',
            'label' => __('WebDAV'),
            'icon' => 'folder_shared',
        ];
    }

    /** Per-Org-Konfiguration liegt in `webdav_connections` (Admin-Panel), nicht in plugin_settings. */
    // ── Backupziel (Feature 123, MVP-612) ───────────────────────────────
    //
    // Generisch, ohne Anbieter-Sonderwege. Das Nextcloud-Ziel bleibt daneben
    // bestehen, weil es Chunked Upload v2 nutzt — den kann ein beliebiger
    // WebDAV-Server nicht.

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

    /** Tests binden hierüber einen Client mit gemocktem Transport. */
    public function backupClient(BackupTargetConnection $connection): WebdavBackupClient {
        return app()->makeWith(WebdavBackupClient::class, [
            'connection' => $connection,
            'allowPrivateTargets' => (bool) config('plugins.webdav.allow_private_targets', false),
        ]);
    }

    public function settingsSchema(): array {
        return [];
    }

    /** Health-Check je Organisation: aktive Ablage suchen und die Collection anpingen. */
    public function healthCheck(): PluginHealth {
        $org = $this->healthOrgContext();
        if ($org instanceof PluginHealth) {
            return $org;
        }

        $connection = WebdavConnection::query()->where('organization_id', $org->id)->first();
        if (! $connection instanceof WebdavConnection) {
            return PluginHealth::degraded(__('Keine WebDAV-Ablage hinterlegt.'));
        }
        if (! $connection->isActive()) {
            return PluginHealth::degraded(__('WebDAV-Ablage ist deaktiviert oder unvollständig.'));
        }

        try {
            return app(WebdavGatewayFactory::class)->for($connection)->ping()
                ? PluginHealth::ok(__('Verbunden mit :url.', ['url' => $connection->base_url]))
                : PluginHealth::failing(__('WebDAV-Ablage nicht erreichbar oder Zugangsdaten ungültig.'), 'unreachable');
        } catch (Throwable $e) {
            return PluginHealth::failing(__('WebDAV-Fehler (:class).', ['class' => class_basename($e)]));
        }
    }
}
