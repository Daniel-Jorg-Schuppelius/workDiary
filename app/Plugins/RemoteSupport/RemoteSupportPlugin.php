<?php
/*
 * Created on   : Wed May 27 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : RemoteSupportPlugin.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Plugins\RemoteSupport;

use App\Enums\Import\ImportEntity;
use App\Models\Asset\Asset;
use App\Models\Integration\{ExternalReference, ImportRun};
use App\Models\Platform\{Organization, User};
use App\Models\Time\TimeEntry;
use App\Plugins\{AbstractPlugin, PluginHealth};
use App\Plugins\Contracts\{NavigationContributor, Plugin, PluginCapability, ProvidesRemoteSessions, SlotRenderer, TimeImporter};
use App\Plugins\RemoteSupport\Enums\RemotePendingSessionStatus;
use App\Plugins\RemoteSupport\Models\RemotePendingSession;
use App\Plugins\RemoteSupport\Providers\{RemoteProvider, RemoteProviderDefinition, RemoteProviders};
use App\Plugins\RemoteSupport\Services\{RemoteDeviceRegistry, RemotePendingAssignmentService, RemoteSessionImporter};
use App\Plugins\Support\{ApiHealthProbe, PluginOrgContext};
use App\Services\Navigation\NavigationRegistry;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\{Cache, Route};

/**
 * Fernwartungs-Plugin (AnyDesk, TeamViewer, RustDesk, Splashtop, pcvisit —
 * Register {@see RemoteProviders}).
 *
 * - Hinterlegt je Gerät (Asset) die Geräte-ID des Anbieters in external_references.
 * - Importiert die Verbindungs-Reports der Dienste und legt je Sitzung einen
 *   TimeEntry im Standardprojekt des zugeordneten Kunden an (TIME_IMPORT).
 *
 * Plugin-Id ist "remote-support". Pro Organisation konfigurierbar über
 * plugin_settings; ENV dient nur als Fallback.
 */
class RemoteSupportPlugin extends AbstractPlugin implements NavigationContributor, ProvidesRemoteSessions, SlotRenderer, TimeImporter {
    public const ID = 'remote-support';

    public const SERVICE_PROVIDER = RemoteSupportServiceProvider::class;

    public function name(): string {
        return (string) __('Fernwartung');
    }

    public function version(): string {
        return '0.1.0';
    }

    public function description(): string {
        return __('Speichert die Geräte-IDs von AnyDesk, TeamViewer, RustDesk, Splashtop und pcvisit an Geräten und importiert Verbindungen als Zeiteinträge im Standardprojekt des Kunden.');
    }

    public function capabilities(): array {
        return [
            PluginCapability::TimeImport,
        ];
    }

    /** Einheitlicher Sync-Einstieg (TimeImporter): importiert Sitzungen über das konfigurierte Zeitfenster. */
    public function importTimeEntries(Organization $organization): array {
        $config = RemoteSupportConfig::resolve($organization->id);
        $days = max(1, (int) $config['sync_window_days']);
        $to = CarbonImmutable::now();

        return app(RemoteSessionImporter::class)->import($organization, $config, $to->subDays($days), $to);
    }

    public function navigationItems(User $user): array {
        if (! Route::has('admin.remote-support.pending.index') || $user->organization_id === null) {
            return [];
        }
        $organizationId = (int) $user->organization_id;
        $pending = (int) Cache::remember(
            'nav-badge:remote-pending:' . $organizationId,
            NavigationRegistry::BADGE_TTL,
            static fn (): int => RemotePendingSession::query()
                ->where('organization_id', $organizationId)
                ->where('status', RemotePendingSessionStatus::Open)
                ->count(),
        );

        return ['admin' => [['route' => 'admin.remote-support.pending.index', 'label' => __('Fernwartung – Inbox'), 'icon' => 'inbox', 'modal' => false, 'badge' => $pending, 'folder' => 'data']]];
    }

    public function navigationMatches(): array {
        return [];
    }

    public function adminPanel(): ?array {
        return [
            'route' => 'admin.plugins.edit',
            'label' => __('Fernwartungs-Einstellungen'),
            'icon' => 'support_agent',
        ];
    }

    public function settingsSchema(): array {
        return [
            ['key' => 'sync_window_days', 'label' => __('Sync-Zeitfenster (Tage)'), 'type' => 'text', 'default' => '2', 'help' => __('Wie viele Tage rückwirkend pro Lauf abgefragt werden.')],
            ['key' => 'default_billable', 'label' => __('Importierte Sitzungen abrechenbar'), 'type' => 'boolean', 'default' => true],
            ['key' => 'default_user_id', 'label' => __('Zeiten buchen für Benutzer'), 'type' => 'user', 'help' => __('Optional. Leer = Organisations-Owner bzw. erster Benutzer.')],

            ...array_merge(...array_map(static fn (RemoteProviderDefinition $d): array => $d->settingsSchema(), array_values(RemoteProviders::all()))),

            // Ein Schlüssel für das ganze Plugin: nur selbst gehostete Server (RustDesk) stehen im LAN.
            ['key' => 'allow_private_network', 'label' => __('Private Netzadressen erlauben'), 'type' => 'boolean', 'default' => false, 'help' => __('Nur für einen selbst gehosteten RustDesk-Server im eigenen Netz (10.x, 192.168.x, …). Cloud-Dienste brauchen das nicht.')],
        ];
    }

    /**
     * Pingt die aktiven Provider. Antwortet mindestens einer, gilt das Plugin
     * als gesund; kein aktiver/konfigurierter Provider → degraded.
     */
    public function healthCheck(): PluginHealth {
        $config = RemoteSupportConfig::resolve();
        $providers = array_filter(app(RemoteSessionImporter::class)->providersFor($config, PluginOrgContext::currentId()), fn ($p) => $p->isConfigured());

        if ($providers === []) {
            return PluginHealth::degraded(__('Kein Fernwartungs-Anbieter konfiguriert.'));
        }

        $results = array_map(
            static fn (RemoteProvider $provider): PluginHealth => ApiHealthProbe::probe(
                RemoteProviders::label($provider->id()),
                fn () => $provider->probe(),
            ),
            array_values($providers),
        );
        $text = implode(' · ', array_map(static fn (PluginHealth $health): string => $health->message, $results));
        $ok = count(array_filter($results, static fn (PluginHealth $health): bool => $health->isOk()));

        // Ein ausgefallener Anbieter schränkt ein, solange ein anderer läuft; erst ohne laufenden zählt der schwerste Befund.
        return match (true) {
            $ok === count($results) => PluginHealth::ok($text),
            $ok > 0 => PluginHealth::degraded($text),
            default => array_any($results, static fn (PluginHealth $health): bool => $health->isFailing())
                ? PluginHealth::failing($text)
                : PluginHealth::degraded($text),
        };
    }

    /**
     * Rendert das Fernwartungs-Panel in der Asset-Detailansicht — nur, wenn das
     * Plugin aktiv ist und das Gerät eine fernwartbare Unterkategorie hat
     * (Arbeitsplatz, Server, Notebook).
     */
    public function renderActions(string $slot, mixed $context = null): ?string {
        if (! $this->isEnabled()) {
            return null;
        }
        if ($slot === 'import-run.notice' && $context instanceof ImportRun) {
            return $context->entity === ImportEntity::RemoteSessions && $context->rows_skipped > 0
                ? view('remote-support::_import_notice', ['skipped' => $context->rows_skipped])->render()
                : null;
        }
        if ($slot !== 'asset-show.aside' || ! $context instanceof Asset) {
            return null;
        }
        if (! in_array($context->category_code, RemoteDeviceRegistry::REMOTE_CATEGORY_CODES, true)) {
            return null;
        }

        $devices = app(RemoteDeviceRegistry::class);

        $organization = $context->organization;
        $pendingCount = $organization instanceof Organization
            ? app(RemotePendingAssignmentService::class)->openPendingGroups($organization)->sum(static fn(object $group): int => (int) $group->count)
            : 0;

        return view('remote-support::_panel', [
            'asset' => $context,
            'providers' => array_map(static fn (RemoteProviderDefinition $d): array => [
                'id' => $d->id,
                'label' => $d->label,
                'placeholder' => $d->placeholder,
                'ids' => $devices->remoteIds($context, $d->id),
            ], array_values(RemoteProviders::all())),
            'pendingCount' => (int) $pendingCount,
            // Ziele für „Fernwartungsdaten übertragen" (Duplikat-Bereinigung).
            'mergeTargets' => \App\Models\Asset\Asset::query()
                ->whereIn('category_code', RemoteDeviceRegistry::REMOTE_CATEGORY_CODES)
                ->whereKeyNot($context->getKey())
                ->orderBy('name')
                ->get(['id', 'name', 'asset_no']),
        ])->render();
    }

    public function remoteSessionsUrl(): string {
        return route('admin.remote-support.pending.index');
    }

    public function recentRemoteSessions(int $organizationId, int $limit): array {
        return array_values(RemotePendingSession::query()
            ->withoutGlobalScopes()
            ->where('organization_id', $organizationId)
            ->orderByDesc('started_at')
            ->limit($limit)
            ->get()
            ->map(static fn(RemotePendingSession $s): array => [
                'provider' => (string) $s->provider,
                'label' => $s->alias !== null && $s->alias !== '' ? (string) $s->alias : (string) $s->remote_id,
                'started_at' => $s->started_at,
                'ended_at' => $s->ended_at,
                'status' => $s->status->value,
            ])
            ->all());
    }

    /** Zuordnung aus den Sitzungsreferenzen (`payload.asset_id`). */
    public function remoteSessionAssets(array $assetIds): array {
        $assetSet = array_fill_keys($assetIds, true);
        $entryToAsset = [];
        ExternalReference::query()
            ->where('plugin_id', self::ID)
            ->where('external_type', 'session')
            ->where('referenceable_type', (new TimeEntry)->getMorphClass())
            ->get(['referenceable_id', 'payload'])
            ->each(function (ExternalReference $ref) use (&$entryToAsset, $assetSet): void {
                $assetId = (int) ($ref->payload['asset_id'] ?? 0);
                if ($assetId > 0 && isset($assetSet[$assetId])) {
                    $entryToAsset[(int) $ref->referenceable_id] = $assetId;
                }
            });

        return $entryToAsset;
    }
}
