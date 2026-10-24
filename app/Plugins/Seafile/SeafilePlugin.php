<?php
/*
 * Created on   : Sat Oct 24 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : SeafilePlugin.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Plugins\Seafile;

use App\Enums\Backup\{BackupProvider, BackupTargetStatus};
use App\Enums\CloudIntake\{CloudIntakeConnectionStatus, CloudIntakeProvider};
use App\Models\Backup\BackupTargetConnection;
use App\Models\CloudIntake\CloudDocumentConnection;
use App\Models\Integration\ExternalReference;
use App\Plugins\{AbstractPlugin, PluginHealth};
use App\Plugins\Contracts\{BackupTarget, BackupTargetConnector, DocumentIntakeOrigins, DocumentIntakeSource, IntakeSourceConnector, LinksExternalRecords, PluginCapability};
use App\Plugins\Seafile\Api\SeafileClientFactory;
use App\Plugins\Seafile\Services\{SeafileBackupTarget, SeafileIntake};
use App\Plugins\Support\ApiHealthProbe;
use App\Plugins\Support\Backup\BackupAccount;
use App\Plugins\Support\Intake\{IntakeAccount, IntakeChangePage, IntakeItem};
use App\Support\Ui\UiAction;
use Illuminate\Database\Eloquent\Model;
use Psr\Http\Message\StreamInterface;

/**
 * Seafile (Feature 177, Phase 158 Welle 4):
 *
 *  - **Dokumenteingang**: Dateien einer gewählten Bibliothek laufen über die
 *    vorhandene Eingangspipeline in Rechnungseingang oder Dokumentablage, nur
 *    lesend. Adresse und Token stehen je Organisation in den
 *    Plugin-Einstellungen.
 *  - **Backupziel**: systemweit, verschlüsselt, in eine gewählte Bibliothek —
 *    eigene Verbindung mit eigenem Token, getrennt vom Eingang.
 */
class SeafilePlugin extends AbstractPlugin implements BackupTarget, BackupTargetConnector, DocumentIntakeOrigins, DocumentIntakeSource, IntakeSourceConnector, LinksExternalRecords {
    public const ID = 'seafile';

    public const SERVICE_PROVIDER = SeafileServiceProvider::class;

    public function name(): string {
        return 'Seafile';
    }

    public function version(): string {
        return '0.1.0';
    }

    public function description(): string {
        return (string) __('seafile::seafile.plugin.description');
    }

    public function settingsSchema(): array {
        return [
            ['key' => 'base_url', 'label' => __('seafile::seafile.settings.base_url'), 'type' => 'text', 'required' => true, 'help' => __('seafile::seafile.settings.base_url_help')],
            ['key' => 'api_token', 'label' => __('seafile::seafile.settings.api_token'), 'type' => 'password', 'required' => true, 'help' => __('seafile::seafile.settings.api_token_help')],
            ['key' => 'allow_private_network', 'label' => __('seafile::seafile.settings.allow_private_network'), 'type' => 'boolean', 'default' => false, 'help' => __('seafile::seafile.settings.allow_private_network_help')],
        ];
    }

    public function capabilities(): array {
        return [PluginCapability::DocumentIntake, PluginCapability::BackupTarget];
    }

    // ── Dokumenteingang ─────────────────────────────────────────────────

    public function intakeAccount(CloudDocumentConnection $connection): IntakeAccount {
        return app(SeafileIntake::class)->account($connection);
    }

    public function intakeContainers(CloudDocumentConnection $connection, ?string $search = null): array {
        return app(SeafileIntake::class)->containers($connection, $search);
    }

    public function intakeChanges(CloudDocumentConnection $connection, ?string $checkpoint): IntakeChangePage {
        return app(SeafileIntake::class)->changes($connection, $checkpoint);
    }

    public function intakeDownload(CloudDocumentConnection $connection, IntakeItem $item): StreamInterface {
        return app(SeafileIntake::class)->download($connection, $item);
    }

    public function intakeImported(CloudDocumentConnection $connection, IntakeItem $item, Model $imported): void {
        app(SeafileIntake::class)->recordOrigin($connection, $item, $imported);
    }

    public function intakeConnectAction(): UiAction {
        return new UiAction('add', (string) __('seafile::seafile.action.connect_intake'), route('admin.cloud-intake.seafile.connect-form'), modal: true);
    }

    public function externalRecordUrl(ExternalReference $reference): ?string {
        return $reference->plugin_id === self::ID ? app(SeafileIntake::class)->sourceUrl($reference) : null;
    }

    // ── Backupziel ──────────────────────────────────────────────────────

    public function backupAccount(BackupTargetConnection $connection): BackupAccount {
        return app(SeafileBackupTarget::class)->account($connection);
    }

    public function backupQuota(BackupTargetConnection $connection): array {
        return app(SeafileBackupTarget::class)->quota($connection);
    }

    public function backupEnsureFolder(BackupTargetConnection $connection, string $path): string {
        return app(SeafileBackupTarget::class)->ensureFolder($connection, $path);
    }

    public function backupList(BackupTargetConnection $connection, string $prefix): array {
        return app(SeafileBackupTarget::class)->listObjects($connection, $prefix);
    }

    public function backupUploadPart(BackupTargetConnection $connection, string $localPath, string $remoteName): string {
        return app(SeafileBackupTarget::class)->uploadPart($connection, $localPath, $remoteName);
    }

    public function backupDownload(BackupTargetConnection $connection, string $remoteRef): StreamInterface {
        return app(SeafileBackupTarget::class)->download($connection, $remoteRef);
    }

    public function backupDelete(BackupTargetConnection $connection, string $remoteRef): bool {
        return app(SeafileBackupTarget::class)->delete($connection, $remoteRef);
    }

    public function backupConnectAction(): UiAction {
        return new UiAction('add', 'Seafile', route('admin.backup-targets.seafile.connect-form'), modal: true);
    }

    public function backupReconnectAction(BackupTargetConnection $connection): UiAction {
        return new UiAction('sync', (string) __('backup_targets.reconnect'), route('admin.backup-targets.seafile.connect-form', ['connection' => $connection->sqid]), modal: true);
    }

    // ── Betrieb ─────────────────────────────────────────────────────────

    public function healthCheck(): PluginHealth {
        // Backupziele sind plattformweit: ein blockiertes Ziel betrifft alle Organisationen.
        $backupAttention = BackupTargetConnection::query()
            ->where('provider', BackupProvider::Seafile)
            ->whereIn('status', [BackupTargetStatus::ReauthRequired, BackupTargetStatus::Blocked])
            ->exists();
        if ($backupAttention) {
            return PluginHealth::degraded((string) __('seafile::seafile.health.backup_attention'), 'backup');
        }

        $organization = $this->healthOrgContext();
        if ($organization instanceof PluginHealth) {
            return $organization;
        }
        if (! SeafileConfig::isConfigured((int) $organization->id)) {
            return PluginHealth::degraded((string) __('seafile::seafile.health.not_configured'), 'not_configured');
        }
        $connection = CloudDocumentConnection::query()
            ->where('organization_id', $organization->id)
            ->where('provider', CloudIntakeProvider::Seafile->value)
            ->first();
        if (! $connection instanceof CloudDocumentConnection) {
            return PluginHealth::degraded((string) __('seafile::seafile.health.not_connected'), 'not_connected');
        }
        if (in_array($connection->status, [CloudIntakeConnectionStatus::ReauthRequired, CloudIntakeConnectionStatus::Blocked], true) || $connection->last_error !== null) {
            return PluginHealth::degraded((string) __('seafile::seafile.health.attention'), 'connection');
        }

        return ApiHealthProbe::probe($this->name(), fn () => app(SeafileClientFactory::class)->for((int) $organization->id)->probe());
    }
}
