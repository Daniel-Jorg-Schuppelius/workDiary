<?php
/*
 * Created on   : Sun Jun 15 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : SecurityOverviewService.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Services\Security;

use App\Models\{AuditLog, ExportRun, ExternalReference, Organization, PluginSetting, TimeExport, User};
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Admin-Sicherheitsübersicht (Feature 016, MVP) — READ-ONLY-Aggregation für
 * die Admin-Seite „Sicherheit". Sie führt sicherheitsrelevante Zustände aus
 * vorhandenen Quellen zusammen, ohne selbst Daten zu speichern oder zu
 * verändern.
 *
 * Bewusste Abgrenzung:
 * - KEINE schreibenden Aktionen (Sessions/Tokens widerrufen liegt — sofern
 *   gewünscht — auf der Privacy-Seite, MVP-005, die separat geführt wird).
 * - Die automatisierten Lösch-/Aufbewahrungsläufe (Feature 016, „Später")
 *   sind NICHT Teil dieser Übersicht.
 *
 * Datenschutz / Geheimnisschutz:
 * - Es werden NIEMALS Token-Werte, Token-Hashes, Secrets, Passwörter,
 *   VAPID-Keys, Session-Payloads oder verschlüsselte Plugin-Settings
 *   ausgegeben — ausschließlich Metadaten (Name, Zeitpunkte, Zähler).
 * - 2FA wird ausschließlich GEZÄHLT (confirmed_at vorhanden) — keine
 *   2FA-Logik wird berührt.
 *
 * Mandanten-Sicht (analog {@see \App\Services\Metrics\OperationsMetricsService}):
 * org-gebundene Modelle (ExportRun, TimeExport, ExternalReference,
 * PluginSetting, AuditLog) tragen BelongsToOrganization und sind bei
 * gebundener `currentOrganization` automatisch org-bezogen. Nicht
 * org-gebundene Tabellen (sessions, personal_access_tokens,
 * two_factor_credentials) werden explizit über die User-IDs der aktuellen
 * Organisation eingeschränkt; ohne Org-Bindung (globaler Plattform-Admin)
 * gilt die plattformweite Sicht.
 */
class SecurityOverviewService {
    /** Anzahl der jüngsten Einträge je Detailliste. */
    public const RECENT_LIMIT = 10;

    /** Eine Session gilt als „aktiv", wenn sie jünger als dieser Zeitraum ist. */
    public const SESSION_ACTIVE_MINUTES = 120;

    /**
     * Sammelt alle Sicherheits-Kennzahlen. Jede Sektion ist gegen Fehler
     * isoliert — eine kaputte Quelle (fehlende Tabelle etc.) reißt die Seite
     * nicht um.
     *
     * @return array<string, mixed>
     */
    public function collect(): array {
        return [
            'scope' => $this->scopeLabel(),
            'sessions' => $this->safe(fn(): array => $this->sessions(), ['available' => false, 'driver' => (string) config('session.driver')]),
            'tokens' => $this->safe(fn(): array => $this->apiTokens(), ['available' => false, 'count' => 0, 'recent' => []]),
            'integrations' => $this->safe(fn(): array => $this->integrations(), ['count' => 0, 'plugins' => [], 'references' => 0]),
            'exports' => $this->safe(fn(): array => $this->exports(), ['recent' => []]),
            'support_access' => $this->safe(fn(): array => $this->supportAccess(), ['count' => 0, 'recent' => []]),
            'two_factor' => $this->safe(fn(): array => $this->twoFactor(), ['users_total' => 0, 'users_with_2fa' => 0, 'credentials' => 0]),
            'encryption' => $this->safe(fn(): array => $this->encryption(), ['fields' => [], 'command' => 'security:encrypt-existing']),
            'generated_at' => CarbonImmutable::now(),
        ];
    }

    /**
     * Aktive Sessions aus der `sessions`-Tabelle (nur beim database-Treiber
     * verfügbar). Zeigt je Eintrag Nutzer, IP und gekürzten User-Agent —
     * NIEMALS das `payload`-Feld (Session-Cookie-Inhalt).
     *
     * @return array<string, mixed>
     */
    private function sessions(): array {
        $driver = (string) config('session.driver');

        if ($driver !== 'database' || ! DB::getSchemaBuilder()->hasTable('sessions')) {
            // Ehrlich anzeigen: bei file/redis/array gibt es keine DB-Übersicht.
            return ['available' => false, 'driver' => $driver];
        }

        $userIds = $this->organizationUserIds();
        $threshold = CarbonImmutable::now()->subMinutes(self::SESSION_ACTIVE_MINUTES)->getTimestamp();

        $base = DB::table('sessions');
        if ($userIds !== null) {
            $base->whereIn('user_id', $userIds);
        }

        $active = (clone $base)->where('last_activity', '>=', $threshold);

        $recent = (clone $base)
            ->orderByDesc('last_activity')
            ->limit(self::RECENT_LIMIT)
            ->get(['id', 'user_id', 'ip_address', 'user_agent', 'last_activity']);

        $names = $this->userNames($recent->pluck('user_id')->filter()->all());

        return [
            'available' => true,
            'driver' => $driver,
            'total' => (int) (clone $base)->count(),
            'active' => (int) $active->count(),
            'recent' => $recent->map(fn(object $row): array => [
                'user' => $row->user_id !== null ? ($names[(int) $row->user_id] ?? ('#' . $row->user_id)) : __('security.field.guest'),
                'ip' => $row->ip_address !== null ? (string) $row->ip_address : null,
                // User-Agent bewusst gekürzt und nur als Metadatum.
                'user_agent' => $this->shortenUserAgent($row->user_agent),
                'last_activity' => $row->last_activity !== null ? CarbonImmutable::createFromTimestamp((int) $row->last_activity) : null,
                'is_active' => $row->last_activity !== null && (int) $row->last_activity >= $threshold,
            ])->all(),
        ];
    }

    /**
     * Aktive Sanctum-Tokens (personal_access_tokens). Gibt ausschließlich
     * Metadaten zurück (Name, Abilities, Zeitstempel) — NIEMALS die Spalten
     * `token` (Hash) oder Klartext-Werte.
     *
     * @return array<string, mixed>
     */
    private function apiTokens(): array {
        if (! DB::getSchemaBuilder()->hasTable('personal_access_tokens')) {
            return ['available' => false, 'count' => 0, 'recent' => []];
        }

        $userIds = $this->organizationUserIds();

        $query = DB::table('personal_access_tokens')->where('tokenable_type', User::class);
        if ($userIds !== null) {
            $query->whereIn('tokenable_id', $userIds);
        }

        // Bewusst KEIN Select auf `token` (Hash) — nur Metadaten-Spalten.
        $recent = (clone $query)
            ->orderByDesc('created_at')
            ->limit(self::RECENT_LIMIT)
            ->get(['id', 'tokenable_id', 'name', 'abilities', 'last_used_at', 'expires_at', 'created_at']);

        $names = $this->userNames($recent->pluck('tokenable_id')->filter()->all());

        return [
            'available' => true,
            'count' => (int) (clone $query)->count(),
            'recent' => $recent->map(function (object $row) use ($names): array {
                $abilities = [];
                if ($row->abilities !== null) {
                    $decoded = json_decode((string) $row->abilities, true);
                    if (is_array($decoded)) {
                        $abilities = array_values(array_filter(array_map('strval', $decoded)));
                    }
                }

                return [
                    'name' => (string) $row->name,
                    'user' => $row->tokenable_id !== null ? ($names[(int) $row->tokenable_id] ?? ('#' . $row->tokenable_id)) : null,
                    'abilities' => $abilities,
                    'last_used_at' => $this->toDate($row->last_used_at),
                    'expires_at' => $this->toDate($row->expires_at),
                    'created_at' => $this->toDate($row->created_at),
                ];
            })->all(),
        ];
    }

    /**
     * Aktive externe Integrationen je Organisation: aktivierte Plugins
     * (plugin_settings.enabled) plus die Anzahl externer Referenzen. Es
     * werden NUR Plugin-IDs und Zähler ausgegeben — niemals die
     * verschlüsselten Plugin-Settings (API-Keys etc.).
     *
     * @return array<string, mixed>
     */
    private function integrations(): array {
        $plugins = PluginSetting::query()
            ->where('enabled', true)
            ->orderBy('plugin_id')
            ->get(['plugin_id'])
            ->map(static fn(PluginSetting $s): string => (string) $s->plugin_id)
            ->unique()
            ->values()
            ->all();

        $references = (int) ExternalReference::query()->count();

        return [
            'count' => count($plugins),
            'plugins' => $plugins,
            'references' => $references,
        ];
    }

    /**
     * Jüngste Daten-/Zeit-Exporte aus den vorhandenen Export-Modellen
     * (ExportRun = Datentransfer, TimeExport = Lohnbüro-Export). Es werden nur
     * Metadaten ausgegeben (Typ, Wer, Wann, Format/Status) — KEINE Inhalte.
     *
     * @return array<string, mixed>
     */
    private function exports(): array {
        $rows = [];

        foreach (ExportRun::query()->with('createdBy:id,name')->orderByDesc('created_at')->limit(self::RECENT_LIMIT)->get() as $run) {
            $rows[] = [
                'kind' => __('security.export.kind.data_transfer'),
                'subject' => $run->entity->value,
                'format' => $run->format->value,
                'status' => $run->state->value,
                'rows' => (int) $run->rows_total,
                'user' => $run->createdBy?->name,
                'created_at' => $run->created_at,
            ];
        }

        foreach (TimeExport::query()->with('creator:id,name')->orderByDesc('created_at')->limit(self::RECENT_LIMIT)->get() as $export) {
            $rows[] = [
                'kind' => __('security.export.kind.time'),
                'subject' => $export->periodLabel() . ' · ' . $export->profile,
                'format' => $export->file_format !== null ? (string) $export->file_format : null,
                'status' => $export->status->value,
                'rows' => (int) $export->rows_count,
                'user' => $export->creator?->name,
                'created_at' => $export->created_at,
            ];
        }

        usort($rows, static fn(array $a, array $b): int => $b['created_at']->getTimestamp() <=> $a['created_at']->getTimestamp());

        return ['recent' => array_slice($rows, 0, self::RECENT_LIMIT)];
    }

    /**
     * Jüngste Supportzugriffe / Plattform-Admin-Zugriffe aus dem AuditLog
     * (Event-Präfix `support.`, siehe
     * ../WorkDiary-Architecture/security/supportzugriff-grundsaetze.md §4.2). Ausgegeben werden
     * nur Metadaten — der `changes`-Block enthält per Definition keine
     * personenbezogenen Nutzdaten.
     *
     * @return array<string, mixed>
     */
    private function supportAccess(): array {
        if (! DB::getSchemaBuilder()->hasTable((new AuditLog())->getTable())) {
            return ['count' => 0, 'recent' => []];
        }

        $query = AuditLog::query()->where('event', 'like', 'support.%');

        $recent = (clone $query)
            ->with('user:id,name')
            ->orderByDesc('created_at')
            ->limit(self::RECENT_LIMIT)
            ->get();

        return [
            'count' => (int) (clone $query)->count(),
            'recent' => $recent->map(static fn(AuditLog $log): array => [
                'event' => (string) $log->event,
                'user' => $log->user?->name,
                'ip' => $log->ip,
                'subject' => $log->auditable_type !== ''
                    ? class_basename((string) $log->auditable_type) . ' #' . $log->auditable_id
                    : null,
                'created_at' => $log->created_at,
            ])->all(),
        ];
    }

    /**
     * 2FA-Kennzahlen — REINE ZÄHLUNG, keine 2FA-Logik. Ein User gilt als
     * 2FA-geschützt, wenn er mindestens einen bestätigten Faktor
     * (confirmed_at IS NOT NULL) hat. Es werden keine Secrets gelesen.
     *
     * @return array<string, int>
     */
    private function twoFactor(): array {
        $userIds = $this->organizationUserIds();

        $usersQuery = User::query();
        if ($userIds !== null) {
            $usersQuery->whereIn('id', $userIds);
        }
        $usersTotal = (int) (clone $usersQuery)->count();

        $withTwoFactor = 0;
        $credentials = 0;

        if (DB::getSchemaBuilder()->hasTable('two_factor_credentials')) {
            $credQuery = DB::table('two_factor_credentials')->whereNotNull('confirmed_at');
            if ($userIds !== null) {
                $credQuery->whereIn('user_id', $userIds);
            }
            $credentials = (int) (clone $credQuery)->count();
            $withTwoFactor = (int) (clone $credQuery)->distinct()->count('user_id');
        }

        return [
            'users_total' => $usersTotal,
            'users_with_2fa' => $withTwoFactor,
            'credentials' => $credentials,
        ];
    }

    /**
     * At-rest-Verschlüsselungs-Status: welche PII-Felder über den
     * `security:encrypt-existing`-Lauf verschlüsselt werden. Statische
     * Konfigurations-Angabe (kein Lesen der verschlüsselten Werte selbst).
     *
     * @return array<string, mixed>
     */
    private function encryption(): array {
        // Spiegelt {@see \App\Console\Commands\EncryptExistingPii::TARGETS}
        // (read-only Statushinweis; keine Daten werden ent-/verschlüsselt).
        $fields = [
            'users' => ['tax_identification_number', 'social_security_number'],
            'contact_bank_accounts' => ['account_holder', 'iban', 'bic'],
            'contact_addresses' => ['street', 'supplement', 'zip', 'city'],
        ];

        return [
            'fields' => $fields,
            'command' => 'security:encrypt-existing',
            'app_key_set' => filled(config('app.key')),
        ];
    }

    // ───────────────────────── Helfer ──────────────────────────────────

    /**
     * User-IDs der aktuell gebundenen Organisation. NULL = plattformweite
     * Sicht (globaler Admin ohne Org-Kontext).
     *
     * @return list<int>|null
     */
    private function organizationUserIds(): ?array {
        $org = $this->currentOrganization();
        if ($org === null) {
            return null;
        }

        return array_values(User::query()
            ->where('organization_id', $org->id)
            ->pluck('id')
            ->map(static fn($id): int => (int) $id)
            ->all());
    }

    /**
     * @param  array<int|string>  $ids
     * @return array<int, string>
     */
    private function userNames(array $ids): array {
        if ($ids === []) {
            return [];
        }

        return User::query()
            ->whereIn('id', $ids)
            ->pluck('name', 'id')
            ->map(static fn($name): string => (string) $name)
            ->all();
    }

    private function shortenUserAgent(?string $userAgent): ?string {
        if ($userAgent === null || $userAgent === '') {
            return null;
        }

        $trimmed = trim($userAgent);

        return mb_strlen($trimmed) > 80 ? mb_substr($trimmed, 0, 80) . '…' : $trimmed;
    }

    private function toDate(mixed $value): ?CarbonImmutable {
        if ($value === null || $value === '') {
            return null;
        }

        try {
            return CarbonImmutable::parse((string) $value);
        } catch (Throwable) {
            return null;
        }
    }

    private function scopeLabel(): string {
        $org = $this->currentOrganization();

        return $org instanceof Organization ? (string) $org->name : __('security.scope.platform');
    }

    private function currentOrganization(): ?Organization {
        if (! app()->bound('currentOrganization')) {
            return null;
        }

        $org = app('currentOrganization');

        return $org instanceof Organization ? $org : null;
    }

    /**
     * @template TValue
     * @param  \Closure(): TValue  $callback
     * @param  TValue  $fallback
     * @return TValue
     */
    private function safe(\Closure $callback, mixed $fallback): mixed {
        try {
            return $callback();
        } catch (Throwable) {
            return $fallback;
        }
    }
}
