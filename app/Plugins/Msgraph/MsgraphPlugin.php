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
use GuzzleHttp\Exception\ConnectException;
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
class MsgraphPlugin extends AbstractPlugin implements \App\Plugins\Contracts\ContactSyncer, \App\Plugins\Contracts\DocumentIntakeSubscriptions, \App\Plugins\Contracts\SlotRenderer, \App\Plugins\Contracts\TaskSyncer, BackupTarget, CalendarPublisher, DocumentIntakeSource {
    public const ID = 'msgraph';

    public const SERVICE_PROVIDER = MsgraphServiceProvider::class;

    /** ExternalReference-Typ des Kontakt-Pushs (Schnitt D). */
    public const EXT_TYPE_CONTACT = 'contact';

    /** ExternalReference-Typ des To-Do-Syncs (Schnitt E). */
    public const EXT_TYPE_TODO_TASK = 'todo_task';

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
            PluginCapability::ContactSync,
            PluginCapability::TaskSync,
            PluginCapability::DocumentIntake,
            PluginCapability::BackupTarget,
        ];
    }

    // ── TaskSyncer (Feature 102, Schnitt E) ─────────────────────────────

    /** @return array{created: int, updated: int, unchanged: int, conflicts: int, inbox: int, failed: int} */
    public function syncTasks(Organization $organization): array {
        return app(\App\Plugins\Msgraph\Services\MsgraphTodoSyncService::class)->syncOrganization($organization);
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

    // ── ContactSyncer (Feature 102, Schnitt D) ──────────────────────────

    /**
     * Pusht den Kunden idempotent als Outlook-Kontakt des verbundenen Kontos:
     * bestehende {@see \App\Models\ExternalReference} ⇒ PATCH; remote gelöscht
     * (404) ⇒ Neuanlage; sonst POST mit Immutable-ID. Keine Dubletten.
     */
    public function pushContact(\App\Models\Customer $customer): string {
        $connection = \App\Models\MsgraphContactConnection::query()
            ->withoutGlobalScopes()
            ->where('organization_id', $customer->organization_id)
            ->first();
        if (! $connection instanceof \App\Models\MsgraphContactConnection || ! $connection->isActive()) {
            throw new \RuntimeException((string) __('msgraph_contacts.flash.no_connection'));
        }

        $client = new \App\Plugins\Msgraph\Api\MsgraphContactsClient($connection);
        $payload = $this->contactPayload($customer);

        $ref = \App\Models\ExternalReference::query()
            ->withoutGlobalScopes()
            ->where('plugin_id', self::ID)
            ->where('external_type', self::EXT_TYPE_CONTACT)
            ->where('referenceable_type', $customer->getMorphClass())
            ->where('referenceable_id', $customer->getKey())
            ->first();

        try {
            $externalId = null;
            if ($ref instanceof \App\Models\ExternalReference && trim((string) $ref->external_id) !== '') {
                if ($client->updateContact((string) $ref->external_id, $payload)) {
                    $externalId = (string) $ref->external_id;
                }
                // 404: remote gelöscht → unten neu anlegen (Referenz wird ersetzt).
            }
            $externalId ??= $client->createContact($payload);
        } catch (\Throwable $e) {
            $connection->recordConnectionFailure(class_basename($e));

            throw $e;
        }

        \App\Models\ExternalReference::query()->withoutGlobalScopes()->updateOrCreate(
            [
                'plugin_id' => self::ID,
                'external_type' => self::EXT_TYPE_CONTACT,
                'referenceable_type' => $customer->getMorphClass(),
                'referenceable_id' => $customer->getKey(),
            ],
            [
                'organization_id' => $customer->organization_id,
                'external_id' => $externalId,
                'synced_at' => now(),
            ],
        );

        $connection->recordConnectionSuccess();
        $connection->markPushed();

        return $externalId;
    }

    /**
     * Graph-`contact`-Struktur aus dem Kunden (nur belegte Felder).
     *
     * @return array<string, mixed>
     */
    private function contactPayload(\App\Models\Customer $customer): array {
        $displayName = trim((string) ($customer->contact_name ?: $customer->name));

        $payload = [
            'displayName' => $displayName,
            'fileAs' => (string) $customer->name,
        ];
        if (trim((string) $customer->company) !== '') {
            $payload['companyName'] = trim((string) $customer->company);
        }
        if (trim((string) $customer->email) !== '') {
            $payload['emailAddresses'] = [['address' => trim((string) $customer->email), 'name' => $displayName]];
        }
        if (trim((string) $customer->phone) !== '') {
            $payload['businessPhones'] = [trim((string) $customer->phone)];
        }
        if (trim((string) $customer->mobile) !== '') {
            $payload['mobilePhone'] = trim((string) $customer->mobile);
        }
        if (trim((string) $customer->homepage) !== '') {
            $payload['businessHomePage'] = trim((string) $customer->homepage);
        }

        $address = array_filter([
            'street' => trim((string) $customer->address_street),
            'postalCode' => trim((string) $customer->address_zip),
            'city' => trim((string) $customer->address_city),
            'countryOrRegion' => trim((string) $customer->country),
        ], static fn (string $value): bool => $value !== '');
        if ($address !== []) {
            $payload['businessAddress'] = $address;
        }

        return $payload;
    }

    // ── SlotRenderer: Push-Button in der Kundenakte (Schnitt D) ─────────

    public function renderActions(string $slot, mixed $context = null): ?string {
        if (! $this->isEnabled()) {
            return null;
        }

        // F: Teams-Presence-Panel der Anwesenheitsseite (Kalender-Verbindung
        // mit erweitertem Scope Presence.Read.All; ohne Scope still aus).
        if ($slot === 'attendance-index.aside') {
            $org = PluginOrgContext::currentOrNull();
            if (! $org instanceof Organization) {
                return null;
            }
            $members = \App\Models\User::query()
                ->where('organization_id', $org->id)
                ->whereNull('customer_id')
                ->whereNull('deactivated_at')
                ->orderBy('name')
                ->limit(50)
                ->get(['id', 'name', 'email']);
            $presence = app(\App\Plugins\Msgraph\Services\MsgraphPresenceService::class)->presenceForUsers($org, $members);
            if ($presence === []) {
                return null; // keine Verbindung/kein Scope → Panel weglassen
            }

            return view('msgraph::attendance.presence', ['members' => $members, 'presence' => $presence])->render();
        }

        // C2: Free/Busy-Prüfung im Termin-Dialog (Kalender-Grant genügt).
        if ($slot === 'event-form.aside') {
            $org = PluginOrgContext::currentOrNull();
            $calendar = $org instanceof Organization
                ? MsgraphConnection::query()->where('organization_id', $org->id)->first()
                : null;
            if (! $calendar instanceof MsgraphConnection || ! $calendar->isActive()) {
                return null;
            }

            return view('msgraph::events.availability')->render();
        }

        if ($slot !== 'customer-show.actions' || ! $context instanceof \App\Models\Customer) {
            return null;
        }

        $connection = \App\Models\MsgraphContactConnection::query()
            ->where('organization_id', $context->organization_id)
            ->first();
        if (! $connection instanceof \App\Models\MsgraphContactConnection || ! $connection->isActive()) {
            return null;
        }

        $url = route('customers.msgraph.contact.push', $context);
        $csrf = csrf_token();
        $label = e((string) __('msgraph_contacts.push_button'));

        return <<<HTML
            <form method="POST" action="{$url}" class="inline">
                <input type="hidden" name="_token" value="{$csrf}">
                <button type="submit" class="btn btn-sm btn-ghost">
                    <span class="material-symbols-outlined" aria-hidden="true">contact_page</span>
                    <span>{$label}</span>
                </button>
            </form>
        HTML;
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

    /**
     * Per-Org-App-Registrierung (Feature 102 Variante B): Organisationen können
     * eine EIGENE Entra-App hinterlegen (encrypted in `plugin_settings`);
     * leer = Instanz-App aus der ENV. Endpunkte/Scopes bleiben BEWUSST
     * config-only ({@see MsgraphConfig}). Redirect-URIs der eigenen App müssen
     * identisch zur Instanz-App registriert sein (Liste zeigt das Admin-Panel,
     * Sektion „Entra-App & tenantweite Freigabe").
     */
    public function settingsSchema(): array {
        return [
            \App\Plugins\Contracts\SettingsField::text('client_id', __('msgraph.settings.client_id'),
                help: __('msgraph.settings.client_id_help'))->toArray(),
            \App\Plugins\Contracts\SettingsField::password('client_secret', __('msgraph.settings.client_secret'),
                help: __('msgraph.settings.client_secret_help'))->toArray(),
            \App\Plugins\Contracts\SettingsField::text('tenant', __('msgraph.settings.tenant'),
                help: __('msgraph.settings.tenant_help'))->toArray(),
        ];
    }

    /**
     * Tenant-Format prüfen (GUID oder Multi-Tenant-Endpunkt); leere Felder
     * sind gültig (Fallback auf die Instanz-App).
     *
     * @param  array<string, mixed>  $settings
     * @return array<string, string>
     */
    public function validateSettings(array $settings): array {
        $tenant = trim((string) ($settings['tenant'] ?? ''));
        if ($tenant !== ''
            && ! in_array(strtolower($tenant), ['common', 'organizations', 'consumers'], true)
            && preg_match('/^[0-9a-fA-F]{8}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{12}$/', $tenant) !== 1
        ) {
            return ['tenant' => (string) __('msgraph.settings.tenant_invalid')];
        }

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
        } catch (ConnectException) {
            // Netzwerk-/DNS-Ausfall ist transient → degraded statt failing,
            // zählt also nicht Richtung Auto-Disable (analog Lexoffice).
            return PluginHealth::degraded(__('msgraph.health.unreachable'), 'network');
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

        // Backupziele sind PLATTFORMWEIT (bewusst ohne organization_id) —
        // ein blockiertes Microsoft-Ziel betrifft alle Organisationen.
        $backup = BackupTargetConnection::query()
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
