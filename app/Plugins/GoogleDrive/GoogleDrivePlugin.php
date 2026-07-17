<?php
/*
 * Created on   : Tue Jul 14 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : GoogleDrivePlugin.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Plugins\GoogleDrive;

use App\Enums\CloudIntake\{CloudIntakeConnectionStatus, CloudIntakeProvider};
use App\Models\Backup\BackupTargetConnection;
use App\Models\CloudIntake\CloudDocumentConnection;
use App\Models\{Organization, PluginSetting};
use App\Plugins\Contracts\{BackupTarget, DocumentIntakeSource, Plugin, PluginCapability};
use App\Plugins\GoogleDrive\Api\{GoogleDriveBackupClient, GoogleDriveClient};
use App\Plugins\{PluginDefaults, PluginHealth};
use App\Plugins\Support\Backup\BackupAccount;
use App\Plugins\Support\Intake\{IntakeAccount, IntakeChangePage, IntakeItem};
use App\Plugins\Support\PluginOrgContext;
use Psr\Http\Message\StreamInterface;
use Throwable;

/**
 * Google Drive als LESENDE Quelle des Cloud-Dokumenteingangs (Feature 080,
 * MVP-355): „Meine Ablage" + Shared Drives, zweiphasiger Checkpoint
 * (files.list ⇒ changes.list), Watch-Channel nur als Aufwecksignal.
 * Produktiver öffentlicher Rollout bleibt bis zur Google-OAuth-Verifikation
 * blockiert (P10/Welle C).
 */
class GoogleDrivePlugin implements BackupTarget, DocumentIntakeSource, Plugin {
    use PluginDefaults;

    public const ID = 'google-drive';

    /** Von der Plugin-Discovery VOR der Instanziierung registriert. */
    public const SERVICE_PROVIDER = GoogleDriveServiceProvider::class;

    public function id(): string {
        return self::ID;
    }

    public function name(): string {
        return 'Google Drive';
    }

    public function version(): string {
        return '1.0.0';
    }

    public function description(): string {
        return __('cloud_intake.google.description');
    }

    public function isEnabled(): bool {
        $org = PluginOrgContext::currentOrNull();
        if ($org instanceof Organization) {
            $row = PluginSetting::forOrganization($org->id, self::ID);
            if ($row->exists) {
                return $row->enabled;
            }
        }

        return (bool) config('plugins.google-drive.enabled', false);
    }

    public function capabilities(): array {
        return [
            PluginCapability::DocumentIntake,
            PluginCapability::BackupTarget,
        ];
    }

    // ── DocumentIntakeSource ────────────────────────────────────────────

    public function intakeAccount(CloudDocumentConnection $connection): IntakeAccount {
        return (new GoogleDriveClient($connection))->account();
    }

    public function intakeContainers(CloudDocumentConnection $connection): array {
        return (new GoogleDriveClient($connection))->containers();
    }

    public function intakeChanges(CloudDocumentConnection $connection, ?string $checkpoint): IntakeChangePage {
        return (new GoogleDriveClient($connection))->changes($checkpoint);
    }

    public function intakeDownload(CloudDocumentConnection $connection, IntakeItem $item): StreamInterface {
        return (new GoogleDriveClient($connection))->download($item);
    }

    // ── BackupTarget (Feature 017 Phase 32, MVP-363) ────────────────────

    public function backupAccount(BackupTargetConnection $connection): BackupAccount {
        return (new GoogleDriveBackupClient($connection))->account();
    }

    public function backupQuota(BackupTargetConnection $connection): array {
        return (new GoogleDriveBackupClient($connection))->quota();
    }

    public function backupEnsureFolder(BackupTargetConnection $connection, string $path): string {
        return (new GoogleDriveBackupClient($connection))->ensureFolder($path);
    }

    public function backupList(BackupTargetConnection $connection, string $prefix): array {
        return (new GoogleDriveBackupClient($connection))->listObjects($prefix);
    }

    public function backupUploadPart(BackupTargetConnection $connection, string $localPath, string $remoteName): string {
        return (new GoogleDriveBackupClient($connection))->uploadPart($localPath, $remoteName);
    }

    public function backupDownload(BackupTargetConnection $connection, string $remoteRef): \Psr\Http\Message\StreamInterface {
        return (new GoogleDriveBackupClient($connection))->download($remoteRef);
    }

    public function backupDelete(BackupTargetConnection $connection, string $remoteRef): bool {
        return (new GoogleDriveBackupClient($connection))->delete($remoteRef);
    }

    // ── Plugin-Verwaltung ───────────────────────────────────────────────

    public function adminPanel(): ?array {
        // Verwaltung läuft über die zentrale Cloud-Dokumenteingang-Seite (P8).
        return null;
    }

    public function serviceProvider(): ?string {
        return GoogleDriveServiceProvider::class;
    }

    /** Keine per-Org-Secrets: Client-ID/-Secret sind installationsweit (ENV). */
    public function settingsSchema(): array {
        return [];
    }

    public function isPerOrganization(): bool {
        return true;
    }

    /** Health je Organisation: Konfiguration + Verbindungszustand (keine API-Probe). */
    public function healthCheck(): PluginHealth {
        if (! GoogleDriveConfig::isConfigured()) {
            return PluginHealth::degraded(__('cloud_intake.google.health.not_configured'));
        }

        $org = PluginOrgContext::currentOrNull();
        if (! $org instanceof Organization) {
            return PluginHealth::ok(__('cloud_intake.google.health.no_org_context'));
        }

        try {
            $failing = CloudDocumentConnection::query()
                ->where('organization_id', $org->id)
                ->where('provider', CloudIntakeProvider::Google->value)
                ->where(function ($query): void {
                    $query->where('status', CloudIntakeConnectionStatus::ReauthRequired->value)
                        ->orWhere('status', CloudIntakeConnectionStatus::Blocked->value);
                })
                ->exists();

            return $failing
                ? PluginHealth::degraded(__('cloud_intake.google.health.attention'))
                : PluginHealth::ok(__('cloud_intake.google.health.ok'));
        } catch (Throwable $e) {
            return PluginHealth::failing(__('cloud_intake.google.health.error', ['class' => class_basename($e)]));
        }
    }
}
