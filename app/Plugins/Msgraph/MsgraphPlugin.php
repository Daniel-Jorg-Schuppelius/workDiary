<?php
/*
 * Created on   : Sun Jul 12 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : MsgraphPlugin.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Plugins\Msgraph;

use App\Models\Backup\BackupTargetConnection;
use App\Models\CloudIntake\CloudDocumentConnection;
use App\Models\{MsgraphConnection, Organization};
use App\Plugins\{AbstractPlugin, PluginHealth};
use App\Plugins\Contracts\{BackupTarget, CalendarPublisher, DocumentIntakeSource, PluginCapability};
use App\Plugins\Msgraph\Api\{MsgraphBackupClient, MsgraphCalendarClient, MsgraphIntakeClient};
use App\Plugins\Support\Backup\BackupAccount;
use App\Plugins\Support\Calendar\{OrganizationEventSource, RemoteCalendarEvent, RemoteCalendarPublishService};
use App\Plugins\Support\Intake\{IntakeAccount, IntakeChangePage, IntakeItem};
use App\Plugins\Support\PluginOrgContext;
use Closure;
use Psr\Http\Message\StreamInterface;
use Throwable;

/**
 * Microsoft-365-Kalender-Anbindung (MVP-328, Bauturbo A8) — Nur-Publish-Pilot
 * neben CalDAV/ICS.
 *
 * - **Publiziert** WorkDiary-Termine ({@see \App\Models\Event}) über Microsoft
 *   Graph in einen wählbaren Kalender des verbundenen M365-Kontos
 *   (OAuth2 Authorization-Code + PKCE, delegated `Calendars.ReadWrite`).
 * - **Idempotent** über stabile UIDs (`transactionId`) +
 *   {@see \App\Models\ExternalReference} (Remote-Event-ID): Anlegen/Ändern/
 *   Löschen (bei Absage) erzeugen keine Dubletten — CalDAV-Muster.
 * - Pro Organisation verbunden ({@see MsgraphConnection}, Tokens verschlüsselt
 *   at-rest); Rückimport externer Termine ist bewusst NICHT Teil des Piloten.
 *
 * Kündigt {@see PluginCapability::CalendarPublish} an; seit Feature 080
 * (MVP-354) zusätzlich {@see PluginCapability::DocumentIntake} — LESENDER
 * Dokumenteingang aus OneDrive/SharePoint über eigene, von der
 * Kalender-Verbindung getrennte {@see CloudDocumentConnection}s.
 */
class MsgraphPlugin extends AbstractPlugin implements \App\Plugins\Contracts\DocumentIntakeSubscriptions, BackupTarget, CalendarPublisher, DocumentIntakeSource {
    public const ID = 'msgraph';

    public const SERVICE_PROVIDER = MsgraphServiceProvider::class;

    public function name(): string {
        return 'Microsoft 365';
    }

    public function version(): string {
        return '0.1.0';
    }

    public function description(): string {
        return __('msgraph.plugin_description');
    }

    public function capabilities(): array {
        return [
            PluginCapability::CalendarPublish,
            PluginCapability::DocumentIntake,
            PluginCapability::BackupTarget,
        ];
    }

    // ── DocumentIntakeSource (Feature 080, MVP-354) ─────────────────────

    public function intakeAccount(CloudDocumentConnection $connection): IntakeAccount {
        return (new MsgraphIntakeClient($connection))->account();
    }

    public function intakeContainers(CloudDocumentConnection $connection, ?string $search = null): array {
        return (new MsgraphIntakeClient($connection))->containers($search);
    }

    public function intakeChanges(CloudDocumentConnection $connection, ?string $checkpoint): IntakeChangePage {
        return (new MsgraphIntakeClient($connection))->changes($checkpoint);
    }

    public function intakeDownload(CloudDocumentConnection $connection, IntakeItem $item): StreamInterface {
        return (new MsgraphIntakeClient($connection))->download($item);
    }

    // ── DocumentIntakeSubscriptions (MS365-Plan §8) ─────────────────────

    public function intakeSubscribe(CloudDocumentConnection $connection): void {
        app(\App\Plugins\Msgraph\Services\MsgraphSubscriptionService::class)->ensure($connection);
    }

    public function intakeUnsubscribe(CloudDocumentConnection $connection): void {
        app(\App\Plugins\Msgraph\Services\MsgraphSubscriptionService::class)->unsubscribe($connection);
    }

    // ── BackupTarget (Feature 017 Phase 32, MVP-363) ────────────────────

    public function backupAccount(BackupTargetConnection $connection): BackupAccount {
        return (new MsgraphBackupClient($connection))->account();
    }

    public function backupQuota(BackupTargetConnection $connection): array {
        return (new MsgraphBackupClient($connection))->quota();
    }

    public function backupEnsureFolder(BackupTargetConnection $connection, string $path): string {
        return (new MsgraphBackupClient($connection))->ensureFolder($path);
    }

    public function backupList(BackupTargetConnection $connection, string $prefix): array {
        return (new MsgraphBackupClient($connection))->listObjects($prefix);
    }

    public function backupUploadPart(BackupTargetConnection $connection, string $localPath, string $remoteName): string {
        return (new MsgraphBackupClient($connection))->uploadPart($localPath, $remoteName);
    }

    public function backupDownload(BackupTargetConnection $connection, string $remoteRef): StreamInterface {
        return (new MsgraphBackupClient($connection))->download($remoteRef);
    }

    public function backupDelete(BackupTargetConnection $connection, string $remoteRef): bool {
        return (new MsgraphBackupClient($connection))->delete($remoteRef);
    }

    /**
     * Einheitlicher Einstieg (CalendarPublisher): publiziert die Termine der
     * Organisation idempotent in den Ziel-Kalender der M365-Verbindung.
     */
    public function publishCalendar(Organization $organization): array {
        return $this->publishItems($organization, fn(): array => app(OrganizationEventSource::class)->itemsFor($organization));
    }

    /**
     * Einzelnes terminartiges Element (MVP-331, Bauturbo A11 — Kalender-Kanal
     * der Benachrichtigungen): identischer idempotenter Publish-Weg, nur mit
     * genau einem Element statt der Org-Terminquelle.
     */
    public function publishCalendarItem(Organization $organization, RemoteCalendarEvent $item): array {
        return $this->publishItems($organization, fn(): array => [$item]);
    }

    /**
     * Gemeinsamer Publish-Kern: aktive Verbindung auflösen, Elemente über den
     * A8-Publish-Service abgleichen; ohne Verbindung stiller No-Op.
     *
     * @param  Closure(): list<RemoteCalendarEvent>  $items
     * @return array{published: int, deleted: int, unchanged: int, failed: int}
     */
    private function publishItems(Organization $organization, Closure $items): array {
        $counters = ['published' => 0, 'deleted' => 0, 'unchanged' => 0, 'failed' => 0];

        $connection = MsgraphConnection::query()->where('organization_id', $organization->id)->first();
        if (! $connection instanceof MsgraphConnection || ! $connection->isActive()) {
            return $counters;
        }

        try {
            $gateway = new MsgraphCalendarClient($connection);
            $result = app(RemoteCalendarPublishService::class)->publish(self::ID, $connection, $gateway, $items());
            foreach ($counters as $key => $value) {
                $counters[$key] = $value + $result[$key];
            }
        } catch (Throwable) {
            $counters['failed']++;
        }

        return $counters;
    }

    public function adminPanel(): ?array {
        return [
            'route' => 'admin.msgraph.index',
            'label' => __('msgraph.title'),
            'icon' => 'event',
        ];
    }

    /** Keine per-Org-Secrets: Client-ID/-Secret/Tenant sind installationsweit (ENV). */
    public function settingsSchema(): array {
        return [];
    }

    /**
     * Health-Check je Organisation: billige Live-Probe über die Kalenderliste;
     * Intake-/Backup-Verbindungen fließen über ihren GESPEICHERTEN Status ein
     * (reauth_required/blocked → degraded), ohne zusätzliche API-Aufrufe.
     */
    public function healthCheck(): PluginHealth {
        if (! MsgraphConfig::isConfigured()) {
            return PluginHealth::degraded(__('msgraph.health.not_configured'));
        }

        $org = PluginOrgContext::currentOrNull();
        if (! $org instanceof Organization) {
            return PluginHealth::ok(__('msgraph.health.no_org_context'));
        }

        $sideNotice = $this->blockedSideConnectionsNotice($org);

        $connection = MsgraphConnection::query()->where('organization_id', $org->id)->first();
        if (! $connection instanceof MsgraphConnection || $connection->status === MsgraphConnection::STATUS_DISCONNECTED) {
            return PluginHealth::degraded($sideNotice ?? __('msgraph.health.no_connection'));
        }
        if (! $connection->isActive()) {
            return PluginHealth::degraded($sideNotice ?? __('msgraph.health.inactive'));
        }

        try {
            if (! (new MsgraphCalendarClient($connection))->ping()) {
                return PluginHealth::failing(__('msgraph.health.failing'), 'unreachable');
            }
        } catch (Throwable $e) {
            return PluginHealth::failing(__('msgraph.health.error', ['class' => class_basename($e)]));
        }

        return $sideNotice !== null
            ? PluginHealth::degraded($sideNotice)
            : PluginHealth::ok(__('msgraph.health.ok'));
    }

    /**
     * Intake-/Backup-Verbindungen der Organisation mit reauth_required/blocked
     * (gespeicherter Lebenszyklus-Status, keine Live-Probe). null = alles ok.
     */
    private function blockedSideConnectionsNotice(Organization $org): ?string {
        $intake = CloudDocumentConnection::query()
            ->where('organization_id', $org->id)
            ->where('provider', \App\Enums\CloudIntake\CloudIntakeProvider::Microsoft)
            ->whereIn('status', [
                \App\Enums\CloudIntake\CloudIntakeConnectionStatus::ReauthRequired,
                \App\Enums\CloudIntake\CloudIntakeConnectionStatus::Blocked,
            ])->count();

        $backup = BackupTargetConnection::query()
            ->where('organization_id', $org->id)
            ->where('provider', \App\Enums\Backup\BackupProvider::Microsoft)
            ->whereIn('status', [
                \App\Enums\Backup\BackupTargetStatus::ReauthRequired,
                \App\Enums\Backup\BackupTargetStatus::Blocked,
            ])->count();

        // Mail-Verbindung (Feature 102): auto-disabled = Versand steht.
        $mail = \App\Models\MsgraphMailConnection::query()
            ->where('organization_id', $org->id)
            ->whereNotNull('disabled_at')
            ->count();

        if ($intake === 0 && $backup === 0 && $mail === 0) {
            return null;
        }

        return __('msgraph.health.side_connections', ['intake' => $intake, 'backup' => $backup, 'mail' => $mail]);
    }
}
