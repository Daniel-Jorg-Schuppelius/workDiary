<?php
/*
 * Created on   : Sat Nov 22 2025
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : PrivacyOverviewService.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Services\Privacy;

use App\Enums\User\Permission;
use App\Models\{AuditLog, Organization, PluginSetting, User};
use App\Plugins\PluginManager;
use App\Services\Security\SessionManagementService;
use Illuminate\Support\Facades\{DB, Schema};

/**
 * Sammelt alle Datenpunkte für die Datenschutzseite (MVP-005 §3).
 *
 * Wurde aus {@see \App\Http\Controllers\Admin\PrivacyController::index()}
 * extrahiert, damit dieselbe Aggregation auch für den Export
 * (`PrivacyController::export`) verfügbar ist. Die Methoden lesen
 * konsequent ACL-bewusst — wer eine Sektion nicht sehen darf, bekommt
 * eine leere Collection.
 *
 * MVP-327 ergänzt §3.5 (externe Integrationen: Config-Dienste + je Org
 * aktivierte Plugins aus `plugin_settings`) und das aggregierte
 * Berichts-Payload für den Datenschutzbericht (§3.9, PDF).
 */
class PrivacyOverviewService {
    public function __construct(private readonly PluginManager $plugins) {}
    /**
     * Liefert das vollständige Aggregat für `$user` und dessen Organisation.
     *
     * Rückgabe-Struktur (alle Schlüssel sind stabil; UI- und Export-Code
     * verlassen sich darauf):
     *
     * @return array<string, mixed>
     */
    public function forUser(User $user, Organization $organization): array {
        $memberIds = User::query()
            ->where('organization_id', $organization->id)
            ->pluck('id');

        $sessions = $user->can(Permission::PrivacySessionsView->value) && Schema::hasTable('sessions')
            ? DB::table('sessions')
            ->whereIn('user_id', $memberIds)
            ->orderByDesc('last_activity')
            ->limit(50)
            ->get(['id', 'user_id', 'ip_address', 'user_agent', 'last_activity'])
            // Die rohe Session-ID verlässt den Dienst nicht (S-54) — die
            // Ansicht braucht nur ein Handle zum Widerrufen.
            ->map(function (object $row): object {
                $row->handle = SessionManagementService::handleFor((string) $row->id);
                unset($row->id);

                return $row;
            })
            : collect();

        $tokens = $user->can(Permission::PrivacyTokensView->value) && Schema::hasTable('personal_access_tokens')
            ? DB::table('personal_access_tokens')
            ->where('tokenable_type', User::class)
            ->whereIn('tokenable_id', $memberIds)
            ->orderByDesc('created_at')
            ->limit(50)
            ->get(['id', 'tokenable_id', 'name', 'abilities', 'last_used_at', 'expires_at', 'created_at'])
            : collect();

        $exports = $user->can(Permission::PrivacyExportsView->value)
            ? AuditLog::query()
            ->where('organization_id', $organization->id)
            ->where('event', 'like', 'tenant.export.%')
            ->orderByDesc('created_at')
            ->limit(20)
            ->get(['id', 'user_id', 'event', 'changes', 'created_at'])
            : collect();

        $supportAccesses = $user->can(Permission::PrivacySupportView->value)
            ? AuditLog::query()
            ->where('organization_id', $organization->id)
            ->where('event', 'like', 'support.%')
            ->orderByDesc('created_at')
            ->limit(20)
            ->get(['id', 'user_id', 'event', 'changes', 'created_at'])
            : collect();

        $integrations = $user->can(Permission::PrivacyIntegrationsView->value)
            ? $this->integrations($organization)
            : [];

        $sessionUsers = User::query()
            ->whereIn('id', $sessions->pluck('user_id')->filter()->unique())
            ->get()->keyBy('id');

        $tokenUsers = User::query()
            ->whereIn('id', $tokens->pluck('tokenable_id')->filter()->unique())
            ->get()->keyBy('id');

        $auditActorIds = $exports->pluck('user_id')
            ->merge($supportAccesses->pluck('user_id'))
            ->filter()
            ->unique();
        $auditActors = User::query()->whereIn('id', $auditActorIds)->get()->keyBy('id');

        return [
            'organization' => $organization,
            'member_ids' => $memberIds,
            'member_count' => $memberIds->count(),
            'sessions' => $sessions,
            'tokens' => $tokens,
            'exports' => $exports,
            'support_accesses' => $supportAccesses,
            'integrations' => $integrations,
            'session_users' => $sessionUsers,
            'token_users' => $tokenUsers,
            'audit_actors' => $auditActors,
            'can' => [
                'sessions_view' => $user->can(Permission::PrivacySessionsView->value),
                'sessions_revoke' => $user->can(Permission::PrivacySessionsRevoke->value),
                'tokens_view' => $user->can(Permission::PrivacyTokensView->value),
                'tokens_revoke' => $user->can(Permission::PrivacyTokensRevoke->value),
                'integrations_view' => $user->can(Permission::PrivacyIntegrationsView->value),
                'exports_view' => $user->can(Permission::PrivacyExportsView->value),
                'support_view' => $user->can(Permission::PrivacySupportView->value),
                'report_export' => $user->can(Permission::PrivacyReportExport->value),
            ],
        ];
    }

    /**
     * §3.5 — Externe Integrationen / Datenflüsse. Zwei Quellen, KEINE eigene
     * Ablage: die instanzweiten Config-Dienste aus der Konzept-Tabelle
     * (Mail, Web-Push, Geocoding, Slack, S3) und die in dieser Organisation
     * aktivierten Plugins (`plugin_settings.enabled`, org-gescopt).
     *
     * Es werden ausschließlich Identität, Konfigurationsquelle, abfließende
     * Datenkategorien und Status ausgegeben — niemals Settings-Inhalte
     * (verschlüsselte API-Keys o. Ä.).
     *
     * @return array<int, array{key: string, name: string, source: string, data: string, status: string, docs_url: string|null, type: string}>
     */
    public function integrations(Organization $organization): array {
        $mailer = (string) config('mail.default', 'log');
        $rows = [
            [
                'key' => 'mail',
                'name' => __('Mail-Versand') . ' (' . $mailer . ')',
                'source' => 'config/mail.php',
                'data' => __('Empfänger-Mailadresse, Betreff, Nachrichtentext'),
                'status' => in_array($mailer, ['log', 'array', ''], true) ? 'inactive' : 'active',
                'docs_url' => null,
                'type' => 'config',
            ],
            [
                'key' => 'webpush',
                'name' => __('Web-Push-Benachrichtigungen'),
                'source' => 'config/webpush.php',
                'data' => __('Push-Endpoint beim Browser-Hersteller, Benachrichtigungs-Payload'),
                'status' => (string) config('webpush.public_key', '') !== '' ? 'active' : 'not_configured',
                'docs_url' => null,
                'type' => 'config',
            ],
            [
                'key' => 'geocoding',
                'name' => __('Geocoding (Nominatim)'),
                'source' => 'config/routing.php',
                'data' => __('Adress-Zeichenketten zur Koordinaten-Auflösung'),
                'status' => (string) config('routing.nominatim.base_url', '') !== '' ? 'active' : 'not_configured',
                'docs_url' => 'https://nominatim.org/',
                'type' => 'config',
            ],
            [
                'key' => 'slack',
                'name' => __('Slack-Benachrichtigungen'),
                'source' => 'config/services.php',
                'data' => __('Benachrichtigungstexte'),
                'status' => (string) config('services.slack.notifications.bot_user_oauth_token', '') !== '' ? 'active' : 'not_configured',
                'docs_url' => 'https://api.slack.com/',
                'type' => 'config',
            ],
            [
                'key' => 's3',
                'name' => __('AWS S3 (Dateiablage)'),
                'source' => 'config/filesystems.php',
                'data' => __('Anhangs-Dateien und Pfadnamen'),
                'status' => config('filesystems.default') === 's3'
                    ? 'active'
                    : ((string) config('filesystems.disks.s3.bucket', '') !== '' ? 'inactive' : 'not_configured'),
                'docs_url' => 'https://aws.amazon.com/s3/',
                'type' => 'config',
            ],
        ];

        $pluginSettings = PluginSetting::query()
            ->withoutGlobalScopes()
            ->where('organization_id', $organization->id)
            ->where('enabled', true)
            ->orderBy('plugin_id')
            ->get(['plugin_id']);

        foreach ($pluginSettings as $setting) {
            $pluginId = (string) $setting->plugin_id;
            $plugin = $this->plugins->find($pluginId);
            $rows[] = [
                'key' => 'plugin:' . $pluginId,
                'name' => $plugin?->name() ?? $pluginId,
                'source' => 'plugin_settings',
                'data' => $plugin?->description() ?? __('Plugin-Integration (Details in der Plugin-Verwaltung)'),
                'status' => 'active',
                'docs_url' => null,
                'type' => 'plugin',
            ];
        }

        return $rows;
    }

    /**
     * §3.9 — Aggregiertes Payload für den Datenschutzbericht (PDF).
     * Bewusst OHNE personenbezogene Detaildaten: nur Zählungen,
     * Konfigurationsstände und Audit-Statistiken (Konzept §3.9/§5).
     *
     * @return array<string, mixed>
     */
    public function reportPayload(Organization $organization): array {
        $memberIds = User::query()
            ->where('organization_id', $organization->id)
            ->pluck('id');

        $sessionCount = Schema::hasTable('sessions')
            ? DB::table('sessions')->whereIn('user_id', $memberIds)->count()
            : 0;

        $tokenCount = Schema::hasTable('personal_access_tokens')
            ? DB::table('personal_access_tokens')
            ->where('tokenable_type', User::class)
            ->whereIn('tokenable_id', $memberIds)
            ->count()
            : 0;

        $exportQuery = fn() => AuditLog::query()
            ->where('organization_id', $organization->id)
            ->where('event', 'like', 'tenant.export.%');
        $supportQuery = fn() => AuditLog::query()
            ->where('organization_id', $organization->id)
            ->where('event', 'like', 'support.%');

        return [
            'generated_at' => now(),
            'organization' => $organization,
            'operating_mode' => (string) config('privacy.operating_mode', 'on_premise'),
            'member_count' => $memberIds->count(),
            'session_count' => $sessionCount,
            'token_count' => $tokenCount,
            'export_count' => $exportQuery()->count(),
            'export_last_at' => $exportQuery()->max('created_at'),
            'support_count' => $supportQuery()->count(),
            'support_last_at' => $supportQuery()->max('created_at'),
            'integrations' => $this->integrations($organization),
        ];
    }

    /**
     * Flacht das Overview-Aggregat für eine maschinenlesbare Ausgabe (JSON/CSV).
     *
     * @param  array<string, mixed>  $overview  Ergebnis von {@see forUser()}
     * @return array<string, mixed>
     */
    public function toExportPayload(array $overview): array {
        /** @var Organization $org */
        $org = $overview['organization'];

        $sessions = collect(is_iterable($overview['sessions'] ?? null) ? $overview['sessions'] : []);
        $tokens = collect(is_iterable($overview['tokens'] ?? null) ? $overview['tokens'] : []);
        $exports = collect(is_iterable($overview['exports'] ?? null) ? $overview['exports'] : []);
        $supportAccesses = collect(is_iterable($overview['support_accesses'] ?? null) ? $overview['support_accesses'] : []);

        return [
            'generated_at' => now()->toIso8601String(),
            'organization' => [
                'id' => $org->id,
                'name' => $org->name,
            ],
            'member_count' => (int) $overview['member_count'],
            'operating_mode' => (string) config('privacy.operating_mode', 'on_premise'),
            'sessions' => $sessions->map(static function ($s): array {
                $row = (array) $s;
                return [
                    'id' => (string) ($row['id'] ?? ''),
                    'user_id' => (int) ($row['user_id'] ?? 0),
                    'ip_address' => $row['ip_address'] ?? null,
                    'last_activity' => (int) ($row['last_activity'] ?? 0),
                ];
            })->values()->all(),
            'tokens' => $tokens->map(static function ($t): array {
                $row = (array) $t;
                return [
                    'id' => (int) ($row['id'] ?? 0),
                    'user_id' => (int) ($row['tokenable_id'] ?? 0),
                    'name' => (string) ($row['name'] ?? ''),
                    'last_used_at' => $row['last_used_at'] ?? null,
                    'expires_at' => $row['expires_at'] ?? null,
                    'created_at' => $row['created_at'] ?? null,
                ];
            })->values()->all(),
            'exports' => $exports->map(static function ($a): array {
                /** @var AuditLog $a */
                return [
                    'id' => (int) $a->id,
                    'user_id' => $a->user_id,
                    'event' => (string) $a->event,
                    'created_at' => $a->created_at?->toIso8601String(),
                ];
            })->values()->all(),
            'support_accesses' => $supportAccesses->map(static function ($a): array {
                /** @var AuditLog $a */
                return [
                    'id' => (int) $a->id,
                    'user_id' => $a->user_id,
                    'event' => (string) $a->event,
                    'created_at' => $a->created_at?->toIso8601String(),
                ];
            })->values()->all(),
            'categories' => (array) config('privacy.categories', []),
        ];
    }
}
