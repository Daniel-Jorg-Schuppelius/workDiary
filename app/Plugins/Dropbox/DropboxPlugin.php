<?php
/*
 * Created on   : Tue Jul 14 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : DropboxPlugin.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Plugins\Dropbox;

use App\Models\Backup\BackupTargetConnection;
use App\Models\CloudIntake\CloudDocumentConnection;
use App\Models\Organization;
use App\Plugins\{AbstractPlugin, PluginHealth};
use App\Plugins\Contracts\{BackupTarget, DocumentIntakeSource, Plugin, PluginCapability};
use App\Plugins\Dropbox\Api\{DropboxBackupClient, DropboxClient};
use App\Plugins\Support\Backup\BackupAccount;
use App\Plugins\Support\Intake\{IntakeAccount, IntakeChangePage, IntakeItem};
use App\Plugins\Support\PluginOrgContext;
use Psr\Http\Message\StreamInterface;
use Throwable;

/**
 * Dropbox als LESENDE Quelle des Cloud-Dokumenteingangs (Feature 080,
 * MVP-353). Identität über Provider-IDs + rev; Cursor-Delta ist die
 * Wahrheit, der Webhook nur Aufwecksignal ({@see Http\Controllers\DropboxWebhookController}).
 *
 * Seit Feature 017 Phase 32 (MVP-363) zusätzlich systemweites
 * Cloud-BACKUPZIEL — eigene Verbindung, eigene (Schreib-)Scopes,
 * strikt getrennt vom Dokumenteingang.
 */
class DropboxPlugin extends AbstractPlugin implements BackupTarget, DocumentIntakeSource {
    public const ID = 'dropbox';

    /** Von der Plugin-Discovery VOR der Instanziierung registriert. */
    public const SERVICE_PROVIDER = DropboxServiceProvider::class;

    public function name(): string {
        return 'Dropbox';
    }

    public function version(): string {
        return '1.0.0';
    }

    public function description(): string {
        return __('cloud_intake.dropbox.description');
    }

    public function capabilities(): array {
        return [
            PluginCapability::DocumentIntake,
            PluginCapability::BackupTarget,
        ];
    }

    // ── DocumentIntakeSource ────────────────────────────────────────────

    public function intakeAccount(CloudDocumentConnection $connection): IntakeAccount {
        return (new DropboxClient($connection))->account();
    }

    public function intakeContainers(CloudDocumentConnection $connection, ?string $search = null): array {
        return (new DropboxClient($connection))->containers(); // Dropbox: keine Container-Suche
    }

    public function intakeChanges(CloudDocumentConnection $connection, ?string $checkpoint): IntakeChangePage {
        return (new DropboxClient($connection))->changes($checkpoint);
    }

    public function intakeDownload(CloudDocumentConnection $connection, IntakeItem $item): StreamInterface {
        return (new DropboxClient($connection))->download($item);
    }

    // ── BackupTarget (Feature 017 Phase 32, MVP-363) ────────────────────

    public function backupAccount(BackupTargetConnection $connection): BackupAccount {
        return (new DropboxBackupClient($connection))->account();
    }

    public function backupQuota(BackupTargetConnection $connection): array {
        return (new DropboxBackupClient($connection))->quota();
    }

    public function backupEnsureFolder(BackupTargetConnection $connection, string $path): string {
        return (new DropboxBackupClient($connection))->ensureFolder($path);
    }

    public function backupList(BackupTargetConnection $connection, string $prefix): array {
        return (new DropboxBackupClient($connection))->listObjects($prefix);
    }

    public function backupUploadPart(BackupTargetConnection $connection, string $localPath, string $remoteName): string {
        return (new DropboxBackupClient($connection))->uploadPart($localPath, $remoteName);
    }

    public function backupDownload(BackupTargetConnection $connection, string $remoteRef): StreamInterface {
        return (new DropboxBackupClient($connection))->download($remoteRef);
    }

    public function backupDelete(BackupTargetConnection $connection, string $remoteRef): bool {
        return (new DropboxBackupClient($connection))->delete($remoteRef);
    }

    // ── Plugin-Verwaltung ───────────────────────────────────────────────

    public function adminPanel(): ?array {
        // Verwaltung läuft über die zentrale Cloud-Dokumenteingang-Seite (P8).
        return null;
    }

    /** Keine per-Org-Secrets: App-Key/-Secret sind installationsweit (ENV). */
    public function settingsSchema(): array {
        return [];
    }

    /** Health je Organisation: Konfiguration + Verbindungszustand (keine API-Probe). */
    public function healthCheck(): PluginHealth {
        if (! DropboxConfig::isConfigured()) {
            return PluginHealth::degraded(__('cloud_intake.dropbox.health.not_configured'));
        }

        $org = PluginOrgContext::currentOrNull();
        if (! $org instanceof Organization) {
            return PluginHealth::ok(__('cloud_intake.dropbox.health.no_org_context'));
        }

        try {
            $failing = CloudDocumentConnection::query()
                ->where('organization_id', $org->id)
                ->where('provider', \App\Enums\CloudIntake\CloudIntakeProvider::Dropbox->value)
                ->where(function ($query): void {
                    $query->where('status', \App\Enums\CloudIntake\CloudIntakeConnectionStatus::ReauthRequired->value)
                        ->orWhere('status', \App\Enums\CloudIntake\CloudIntakeConnectionStatus::Blocked->value);
                })
                ->exists();

            if ($failing) {
                return PluginHealth::degraded(__('cloud_intake.dropbox.health.attention'));
            }

            // Backupziele sind PLATTFORMWEIT (bewusst ohne organization_id) —
            // ein blockiertes Ziel betrifft alle Organisationen (Muster Msgraph).
            $backupAttention = BackupTargetConnection::query()
                ->where('provider', \App\Enums\Backup\BackupProvider::Dropbox)
                ->whereIn('status', [
                    \App\Enums\Backup\BackupTargetStatus::ReauthRequired,
                    \App\Enums\Backup\BackupTargetStatus::Blocked,
                ])->exists();

            return $backupAttention
                ? PluginHealth::degraded(__('cloud_intake.dropbox.health.backup_attention'), 'backup_grant')
                : PluginHealth::ok(__('cloud_intake.dropbox.health.ok'));
        } catch (Throwable $e) {
            return PluginHealth::failing(__('cloud_intake.dropbox.health.error', ['class' => class_basename($e)]));
        }
    }
}
