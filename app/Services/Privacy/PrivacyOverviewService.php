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
use App\Models\{AuditLog, Organization, User};
use Illuminate\Support\Facades\{DB, Schema};

/**
 * Sammelt alle Datenpunkte für die Datenschutzseite (MVP-005 §3).
 *
 * Wurde aus {@see \App\Http\Controllers\Admin\PrivacyController::index()}
 * extrahiert, damit dieselbe Aggregation auch für den Export
 * (`PrivacyController::export`) verfügbar ist. Die Methoden lesen
 * konsequent ACL-bewusst — wer eine Sektion nicht sehen darf, bekommt
 * eine leere Collection.
 */
class PrivacyOverviewService {
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
            'session_users' => $sessionUsers,
            'token_users' => $tokenUsers,
            'audit_actors' => $auditActors,
            'can' => [
                'sessions_view' => $user->can(Permission::PrivacySessionsView->value),
                'sessions_revoke' => $user->can(Permission::PrivacySessionsRevoke->value),
                'tokens_view' => $user->can(Permission::PrivacyTokensView->value),
                'tokens_revoke' => $user->can(Permission::PrivacyTokensRevoke->value),
                'exports_view' => $user->can(Permission::PrivacyExportsView->value),
                'support_view' => $user->can(Permission::PrivacySupportView->value),
                'report_export' => $user->can(Permission::PrivacyReportExport->value),
            ],
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
