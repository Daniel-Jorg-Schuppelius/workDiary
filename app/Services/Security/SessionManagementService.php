<?php
/*
 * Created on   : Wed Jul 16 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : SessionManagementService.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Services\Security;

use App\Models\{AttendanceTerminal, AuditLog, LocationDeviceToken, Organization, RemotePendingSession, User};
use App\Support\Sqid;
use Carbon\CarbonImmutable;
use CommonToolkit\Helper\Data\UserAgentHelper;
use CommonToolkit\Helper\Geo\IpLocationHelper;
use Illuminate\Support\Facades\{DB, Schema};
use Laravel\Sanctum\PersonalAccessToken;

/**
 * Aggregiert die aktiven Anmeldungen einer Organisation für die Admin-Ansicht
 * „Angemeldete Nutzer" (Feature 085) und gruppiert sie je Nutzer.
 *
 * Datenschutz/Geheimnisschutz: gibt ausschließlich Metadaten zurück (IP,
 * User-Agent, Zeitpunkte) — NIEMALS `payload` (Session-Cookie) oder den
 * Token-Hash. Session-Auflistung/-Widerruf funktionieren nur beim
 * `database`-Treiber; bei file/redis meldet der Service ehrlich `available:
 * false` (dieselbe Abgrenzung wie {@see SecurityOverviewService}).
 *
 * Die „Anmeldezeit" wird aus dem AuditLog (`auth.login`) je Nutzer aufgelöst
 * (treiberunabhängig); eine exakte Zuordnung Login→Session leistet Laravel
 * nicht, daher ist es der letzte Login des Nutzers, nicht der der Sitzung.
 */
class SessionManagementService {
    /** „Online jetzt": jünger als diese Schwelle (kurz, für Live-Sicht). */
    public const ONLINE_MINUTES = 5;

    /** Anzahl der jüngsten Fernwartungssitzungen in der read-only Historie. */
    public const REMOTE_RECENT = 15;

    /**
     * Baut das je Nutzer gruppierte Aggregat für eine Organisation.
     *
     * @param  string|null  $currentSessionId  Session-ID des Aufrufers (markiert
     *                                          die eigene Sitzung → kein Selbst-Aussperren).
     * @return array{
     *     driver: string,
     *     available: bool,
     *     online_threshold: int,
     *     users: list<array<string, mixed>>,
     *     terminals: list<array<string, mixed>>,
     *     remote_support: list<array<string, mixed>>,
     *     totals: array{users: int, sessions: int, online: int, tokens: int, devices: int}
     * }
     */
    public function forOrganization(Organization $organization, ?string $currentSessionId = null): array {
        $driver = (string) config('session.driver');
        $available = $driver === 'database' && Schema::hasTable('sessions');
        $threshold = CarbonImmutable::now()->subMinutes(self::ONLINE_MINUTES)->getTimestamp();

        $members = User::query()
            ->where('organization_id', $organization->id)
            ->orderBy('name')
            ->get(['id', 'name', 'email']);
        $memberIds = array_map('intval', array_values($members->pluck('id')->all()));

        $sessionsByUser = $available ? $this->sessionsByUser($memberIds, $threshold, $currentSessionId) : [];
        $tokensByUser = Schema::hasTable('personal_access_tokens') ? $this->tokensByUser($memberIds) : [];
        $devicesByUser = Schema::hasTable('location_device_tokens') ? $this->locationDevicesByUser($memberIds) : [];
        $lastLoginByUser = $this->lastLoginByUser($organization, $memberIds);

        $users = [];
        $totalSessions = 0;
        $totalOnline = 0;
        $totalTokens = 0;
        $totalDevices = 0;

        foreach ($members as $member) {
            $sessions = $sessionsByUser[$member->id] ?? [];
            $tokens = $tokensByUser[$member->id] ?? [];
            $devices = $devicesByUser[$member->id] ?? [];
            if ($sessions === [] && $tokens === [] && $devices === []) {
                continue; // Nur Nutzer mit aktiven Anmeldungen/Geräten anzeigen.
            }

            $online = count(array_filter($sessions, static fn(array $s): bool => $s['is_online']));
            $totalSessions += count($sessions);
            $totalOnline += $online;
            $totalTokens += count($tokens);
            $totalDevices += count($devices);

            $users[] = [
                'user_id' => (int) $member->id,
                'sqid' => Sqid::encode(User::class, (int) $member->id),
                'name' => (string) $member->name,
                'email' => (string) $member->email,
                'sessions' => $sessions,
                'tokens' => $tokens,
                'location_devices' => $devices,
                'session_count' => count($sessions),
                'token_count' => count($tokens),
                'device_count' => count($devices),
                'is_online' => $online > 0,
                'last_login_at' => $lastLoginByUser[$member->id] ?? null,
            ];
        }

        // Online-Nutzer zuerst, dann alphabetisch (Reihenfolge kam aus der Query).
        usort($users, static fn(array $a, array $b): int => ($b['is_online'] <=> $a['is_online']) ?: strcasecmp($a['name'], $b['name']));

        return [
            'driver' => $driver,
            'available' => $available,
            'online_threshold' => $threshold,
            'users' => $users,
            // Org-weite Geräte-/Fernwartungsquellen (Feature 085, Phase 3):
            'terminals' => $this->terminals($organization, $threshold),
            'remote_support' => $this->remoteSupportSessions($organization),
            'totals' => [
                'users' => count($users),
                'sessions' => $totalSessions,
                'online' => $totalOnline,
                'tokens' => $totalTokens,
                'devices' => $totalDevices,
            ],
        ];
    }

    /**
     * @param  list<int>  $memberIds
     * @return array<int, list<array<string, mixed>>>
     */
    private function sessionsByUser(array $memberIds, int $threshold, ?string $currentSessionId): array {
        if ($memberIds === []) {
            return [];
        }

        $rows = DB::table('sessions')
            ->whereIn('user_id', $memberIds)
            ->orderByDesc('last_activity')
            ->get(['id', 'user_id', 'ip_address', 'user_agent', 'last_activity']);

        $grouped = [];
        foreach ($rows as $row) {
            $userId = (int) $row->user_id;
            $lastActivity = (int) $row->last_activity;
            $userAgent = $row->user_agent !== null ? (string) $row->user_agent : null;
            $ip = $row->ip_address !== null ? (string) $row->ip_address : null;
            $grouped[$userId][] = [
                'id' => (string) $row->id,
                'ip' => $ip,
                'user_agent' => $userAgent,
                // Anreicherung (Feature 085, Phase 2): lesbares Gerät/Browser-Label
                // und grober Standort — beides rein für die Anzeige.
                'device_label' => UserAgentHelper::shortLabel($userAgent),
                'device_type' => UserAgentHelper::deviceType($userAgent),
                'location' => $this->formatLocation(IpLocationHelper::lookup($ip)),
                'last_activity' => CarbonImmutable::createFromTimestamp($lastActivity),
                'is_online' => $lastActivity >= $threshold,
                'is_current' => $currentSessionId !== null && (string) $row->id === $currentSessionId,
            ];
        }

        return $grouped;
    }

    /**
     * @param  list<int>  $memberIds
     * @return array<int, list<array<string, mixed>>>
     */
    private function tokensByUser(array $memberIds): array {
        if ($memberIds === []) {
            return [];
        }

        // Bewusst KEIN Select auf `token` (Hash) — nur Metadaten.
        $rows = DB::table('personal_access_tokens')
            ->where('tokenable_type', User::class)
            ->whereIn('tokenable_id', $memberIds)
            ->orderByDesc('created_at')
            ->get(['id', 'tokenable_id', 'name', 'abilities', 'last_used_at', 'created_at']);

        $grouped = [];
        foreach ($rows as $row) {
            $abilities = [];
            if ($row->abilities !== null) {
                $decoded = json_decode((string) $row->abilities, true);
                if (is_array($decoded)) {
                    $abilities = array_values(array_filter(array_map('strval', $decoded)));
                }
            }

            $grouped[(int) $row->tokenable_id][] = [
                'id' => (int) $row->id,
                'sqid' => Sqid::encode(PersonalAccessToken::class, (int) $row->id),
                'name' => (string) $row->name,
                'abilities' => $abilities,
                'last_used_at' => $this->toDate($row->last_used_at),
                'created_at' => $this->toDate($row->created_at),
            ];
        }

        return $grouped;
    }

    /**
     * Registrierte Standort-Erfassungsgeräte je Nutzer (nur nicht widerrufene).
     *
     * @param  list<int>  $memberIds
     * @return array<int, list<array<string, mixed>>>
     */
    private function locationDevicesByUser(array $memberIds): array {
        if ($memberIds === []) {
            return [];
        }

        $rows = DB::table('location_device_tokens')
            ->whereIn('user_id', $memberIds)
            ->whereNull('revoked_at')
            ->orderByDesc('last_used_at')
            ->get(['id', 'user_id', 'label', 'last_used_at']);

        $grouped = [];
        foreach ($rows as $row) {
            $grouped[(int) $row->user_id][] = [
                'id' => (int) $row->id,
                'sqid' => Sqid::encode(LocationDeviceToken::class, (int) $row->id),
                'label' => (string) $row->label,
                'last_used_at' => $this->toDate($row->last_used_at),
            ];
        }

        return $grouped;
    }

    /**
     * Org-weite Stempelterminals mit Health-Status (physische Geräte, KEIN
     * Nutzer-Login). `last_seen_at` jünger als $threshold ⇒ online.
     *
     * @return list<array<string, mixed>>
     */
    private function terminals(Organization $organization, int $threshold): array {
        if (! Schema::hasTable((new AttendanceTerminal())->getTable())) {
            return [];
        }

        return array_values(AttendanceTerminal::query()
            ->withoutGlobalScopes()
            ->where('organization_id', $organization->id)
            ->orderByDesc('active')
            ->orderByDesc('last_seen_at')
            ->get()
            ->map(static fn(AttendanceTerminal $t): array => [
                'sqid' => Sqid::encode(AttendanceTerminal::class, (int) $t->id),
                'name' => (string) $t->name,
                'active' => (bool) $t->active,
                'last_seen_at' => $t->last_seen_at,
                'is_online' => $t->last_seen_at !== null && $t->last_seen_at->getTimestamp() >= $threshold,
            ])
            ->all());
    }

    /**
     * Letzte importierte Fernwartungssitzungen (TeamViewer/AnyDesk) — reine
     * read-only Historie; aus workDiary NICHT beendbar.
     *
     * @return list<array<string, mixed>>
     */
    private function remoteSupportSessions(Organization $organization): array {
        if (! Schema::hasTable((new RemotePendingSession())->getTable())) {
            return [];
        }

        return array_values(RemotePendingSession::query()
            ->withoutGlobalScopes()
            ->where('organization_id', $organization->id)
            ->orderByDesc('started_at')
            ->limit(self::REMOTE_RECENT)
            ->get()
            ->map(static fn(RemotePendingSession $s): array => [
                'provider' => (string) $s->provider,
                'label' => $s->alias !== null && $s->alias !== '' ? (string) $s->alias : (string) $s->remote_id,
                'started_at' => $s->started_at,
                'ended_at' => $s->ended_at,
                'status' => (string) $s->status,
            ])
            ->all());
    }

    /**
     * Letzter erfolgreicher Login je Nutzer aus dem AuditLog (org-gescopt).
     *
     * @param  list<int>  $memberIds
     * @return array<int, CarbonImmutable>
     */
    private function lastLoginByUser(Organization $organization, array $memberIds): array {
        $table = (new AuditLog())->getTable();
        if ($memberIds === [] || ! Schema::hasTable($table)) {
            return [];
        }

        // Query-Builder statt Eloquent: reine Aggregatzeilen (user_id, MAX)
        // ohne Model-Overhead; org-Filter wird explizit gesetzt.
        $rows = DB::table($table)
            ->where('organization_id', $organization->id)
            ->where('event', 'auth.login')
            ->whereIn('user_id', $memberIds)
            ->groupBy('user_id')
            ->selectRaw('user_id, MAX(created_at) as last_login')
            ->get();

        $result = [];
        foreach ($rows as $row) {
            if ($row->user_id !== null && $row->last_login !== null) {
                $result[(int) $row->user_id] = CarbonImmutable::parse((string) $row->last_login);
            }
        }

        return $result;
    }

    private function toDate(mixed $value): ?CarbonImmutable {
        if ($value === null || $value === '') {
            return null;
        }

        return CarbonImmutable::parse((string) $value);
    }

    /**
     * Formt das grobe Geo-Ergebnis in ein Anzeige-Label „Stadt, Land" (was
     * verfügbar ist). Ohne konfigurierte GeoDB liefert lookup() null → null.
     *
     * @param  array{country: string|null, country_iso: string|null, city: string|null}|null  $loc
     */
    private function formatLocation(?array $loc): ?string {
        if ($loc === null) {
            return null;
        }

        $parts = array_values(array_filter([$loc['city'] ?? null, $loc['country'] ?? null], static fn($v): bool => is_string($v) && $v !== ''));

        return $parts === [] ? null : implode(', ', $parts);
    }
}
