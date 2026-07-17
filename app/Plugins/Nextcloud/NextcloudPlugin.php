<?php
/*
 * Created on   : Wed Jul 16 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : NextcloudPlugin.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Plugins\Nextcloud;

use App\Enums\CloudIntake\{CloudIntakeConnectionStatus, CloudIntakeProvider};
use App\Models\Backup\BackupTargetConnection;
use App\Models\CloudIntake\CloudDocumentConnection;
use App\Models\{Organization, PluginSetting};
use App\Plugins\Contracts\{BackupTarget, DocumentIntakeSource, Plugin, PluginCapability};
use App\Plugins\Nextcloud\Api\{NextcloudBackupClient, NextcloudIntakeClient};
use App\Plugins\{PluginDefaults, PluginHealth};
use App\Plugins\Support\Backup\BackupAccount;
use App\Plugins\Support\Intake\{IntakeAccount, IntakeChangePage, IntakeItem};
use App\Plugins\Support\PluginOrgContext;
use Psr\Http\Message\StreamInterface;
use Throwable;

/**
 * Nextcloud über WebDAV (Login Flow v2 / widerrufbares App-Passwort).
 *
 *  - **Dokumenteingang** (Feature 080, MVP-382): lesende Quelle je Organisation
 *    ({@see DocumentIntakeSource}); Identität `oc:fileid` + ETag, budgetierter
 *    rekursiver Scan statt Server-Cursor.
 *  - **Backupziel** (Feature 017 Phase 32, MVP-383): systemweites,
 *    verschlüsseltes Ziel ({@see BackupTarget}) — eigene Verbindung, strikt
 *    getrennt vom Dokumenteingang.
 *
 * Kein installationsweiter App-Key: angebunden wird je Verbindung mit
 * Server-URL, Nutzer und verschlüsseltem App-Passwort.
 */
class NextcloudPlugin implements BackupTarget, DocumentIntakeSource, Plugin {
    use PluginDefaults;

    public const ID = 'nextcloud';

    /** Von der Plugin-Discovery VOR der Instanziierung registriert. */
    public const SERVICE_PROVIDER = NextcloudServiceProvider::class;

    public function id(): string {
        return self::ID;
    }

    public function name(): string {
        return 'Nextcloud';
    }

    public function version(): string {
        return '1.0.0';
    }

    public function description(): string {
        return __('cloud_intake.nextcloud.description');
    }

    public function isEnabled(): bool {
        $org = PluginOrgContext::currentOrNull();
        if ($org instanceof Organization) {
            $row = PluginSetting::forOrganization($org->id, self::ID);
            if ($row->exists) {
                return $row->enabled;
            }
        }

        return (bool) config('plugins.nextcloud.enabled', false);
    }

    public function capabilities(): array {
        return [
            PluginCapability::DocumentIntake,
            PluginCapability::BackupTarget,
        ];
    }

    // ── DocumentIntakeSource (Feature 080, MVP-382) ─────────────────────

    public function intakeAccount(CloudDocumentConnection $connection): IntakeAccount {
        return (new NextcloudIntakeClient($connection))->account();
    }

    public function intakeContainers(CloudDocumentConnection $connection): array {
        return (new NextcloudIntakeClient($connection))->containers();
    }

    public function intakeChanges(CloudDocumentConnection $connection, ?string $checkpoint): IntakeChangePage {
        return (new NextcloudIntakeClient($connection))->changes($checkpoint);
    }

    public function intakeDownload(CloudDocumentConnection $connection, IntakeItem $item): StreamInterface {
        return (new NextcloudIntakeClient($connection))->download($item);
    }

    // ── BackupTarget (Feature 017 Phase 32, MVP-383) ────────────────────

    public function backupAccount(BackupTargetConnection $connection): BackupAccount {
        return (new NextcloudBackupClient($connection))->account();
    }

    public function backupQuota(BackupTargetConnection $connection): array {
        return (new NextcloudBackupClient($connection))->quota();
    }

    public function backupEnsureFolder(BackupTargetConnection $connection, string $path): string {
        return (new NextcloudBackupClient($connection))->ensureFolder($path);
    }

    public function backupList(BackupTargetConnection $connection, string $prefix): array {
        return (new NextcloudBackupClient($connection))->listObjects($prefix);
    }

    public function backupUploadPart(BackupTargetConnection $connection, string $localPath, string $remoteName): string {
        return (new NextcloudBackupClient($connection))->uploadPart($localPath, $remoteName);
    }

    public function backupDownload(BackupTargetConnection $connection, string $remoteRef): StreamInterface {
        return (new NextcloudBackupClient($connection))->download($remoteRef);
    }

    public function backupDelete(BackupTargetConnection $connection, string $remoteRef): bool {
        return (new NextcloudBackupClient($connection))->delete($remoteRef);
    }

    // ── Plugin-Verwaltung ───────────────────────────────────────────────

    public function adminPanel(): ?array {
        // Verwaltung läuft über die zentralen Seiten Cloud-Dokumenteingang
        // (Import) und Cloud-Backupziele (Backup).
        return null;
    }

    public function serviceProvider(): ?string {
        return NextcloudServiceProvider::class;
    }

    /** Zugangsdaten liegen je Verbindung (Server-URL/Nutzer/App-Passwort), nicht in plugin_settings. */
    public function settingsSchema(): array {
        return [];
    }

    public function isPerOrganization(): bool {
        return true;
    }

    /** Health je Organisation: Zustand der Dokumenteingang-Verbindungen. */
    public function healthCheck(): PluginHealth {
        $org = PluginOrgContext::currentOrNull();
        if (! $org instanceof Organization) {
            return PluginHealth::ok(__('cloud_intake.nextcloud.health.no_org_context'));
        }

        try {
            $failing = CloudDocumentConnection::query()
                ->where('organization_id', $org->id)
                ->where('provider', CloudIntakeProvider::Nextcloud->value)
                ->where(function ($query): void {
                    $query->where('status', CloudIntakeConnectionStatus::ReauthRequired->value)
                        ->orWhere('status', CloudIntakeConnectionStatus::Blocked->value);
                })
                ->exists();

            return $failing
                ? PluginHealth::degraded(__('cloud_intake.nextcloud.health.attention'))
                : PluginHealth::ok(__('cloud_intake.nextcloud.health.ok'));
        } catch (Throwable $e) {
            return PluginHealth::failing(__('cloud_intake.nextcloud.health.error', ['class' => class_basename($e)]));
        }
    }
}
