<?php
/*
 * Created on   : Thu Nov 12 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : OrganizationLockdownService.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Auth\LoginPolicy;

use App\Enums\Notification\NotificationEvent;
use App\Enums\Security\SecurityEventType;
use App\Enums\User\UserRole;
use App\Models\Auth\OrganizationLoginPolicy;
use App\Models\Mcp\{McpOAuthCode, McpOAuthRefreshToken};
use App\Models\Platform\{Organization, User};
use App\Notifications\GenericEventNotification;
use App\Services\Org\OrganizationRoles;
use App\Services\Security\SecurityEventLogger;
use App\Support\MorphMap;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\{DB, Log, Notification};
use Laravel\Sanctum\PersonalAccessToken;
use Spatie\Permission\PermissionRegistrar;

/**
 * Notaus je Organisation (Feature 205, MVP-1657).
 *
 * Auslösen beendet alle Sitzungen der Mitglieder außer der auslösenden,
 * entwertet „angemeldet bleiben“ und löscht alle persönlichen API-Tokens
 * samt offener MCP-Autorisierungscodes und Refresh-Tokens. Neue Anmeldungen
 * gelingen nur Org-Admins und Inhabern des Rechts `auth.lockdown`, bis jemand
 * mit diesem Recht oder der Plattformbetrieb aufhebt.
 *
 * Nicht betroffen: Stempelterminals, Standortgeräte, SCIM und andere
 * Integrationen (eigene Gerätetokens), Portal-Konten (eigene Regeln), der
 * Plattformbetrieb und alle anderen Organisationen.
 *
 * Sitzungen anderer Speicher als der Datenbank lassen sich nicht zentral
 * löschen; sie enden über den Anmeldestempel je Anfrage
 * ({@see LoginPolicyEvaluator}).
 */
final class OrganizationLockdownService {
    public const int REASON_MAX = 2000;

    private const int CHUNK = 500;

    public function __construct(private readonly SecurityEventLogger $security) {}

    /** @return array{users: int, sessions: int, tokens: int} */
    public function engage(Organization $organization, User $actor, string $reason, ?string $keepSessionId): array {
        $policy = $this->policy($organization, $actor);
        $policy->forceFill([
            'lockdown_at' => now(),
            'lockdown_by' => $actor->id,
            'lockdown_reason' => mb_substr(trim($reason), 0, self::REASON_MAX),
            'updated_by' => $actor->id,
        ])->save();

        $members = $this->memberIds($organization);
        $counts = DB::transaction(function () use ($members, $keepSessionId): array {
            $sessions = 0;
            $tokens = 0;
            foreach (array_chunk($members, self::CHUNK) as $chunk) {
                if (config('session.driver') === 'database') {
                    $query = DB::table('sessions')->whereIn('user_id', $chunk);
                    if ($keepSessionId !== null && $keepSessionId !== '') {
                        $query->where('id', '!=', $keepSessionId);
                    }
                    $sessions += $query->delete();
                }

                // „Angemeldet bleiben“ entwerten, ohne je Konto ein Update samt Audit zu schreiben.
                DB::table('users')->whereIn('id', $chunk)->update(['remember_token' => null]);

                $tokens += PersonalAccessToken::query()
                    ->where('tokenable_type', MorphMap::alias(User::class))
                    ->whereIn('tokenable_id', $chunk)
                    ->delete();

                // Offene MCP-Autorisierungscodes und Refresh-Tokens: sonst entstünde
                // aus einem vorher erteilten Code nach dem Notaus ein neues Token.
                McpOAuthCode::query()->whereIn('user_id', $chunk)->delete();
                McpOAuthRefreshToken::query()->whereIn('user_id', $chunk)->delete();
            }

            return ['users' => count($members), 'sessions' => $sessions, 'tokens' => $tokens];
        });

        $this->security->log(SecurityEventType::OrganizationLockdown, [
            'user' => $actor->email,
            'user_id' => (int) $actor->id,
            'organization_id' => (int) $organization->id,
            'action' => 'engaged',
            'reason' => $policy->lockdown_reason,
            'sessions' => $counts['sessions'],
            'tokens' => $counts['tokens'],
        ]);
        $this->notifyAdmins($organization, $actor, 'engaged', (string) $policy->lockdown_reason);

        return $counts;
    }

    public function lift(Organization $organization, User $actor): void {
        $policy = OrganizationLoginPolicy::query()->withoutGlobalScopes()->where('organization_id', $organization->id)->first();
        if (! $policy instanceof OrganizationLoginPolicy || ! $policy->isLockedDown()) {
            return;
        }

        $policy->forceFill([
            'lockdown_at' => null,
            'lockdown_by' => null,
            'lockdown_reason' => null,
            'updated_by' => $actor->id,
        ])->save();

        $this->security->log(SecurityEventType::OrganizationLockdown, [
            'user' => $actor->email,
            'user_id' => (int) $actor->id,
            'organization_id' => (int) $organization->id,
            'action' => 'lifted',
            'platform' => $actor->isGlobalAdmin() && (int) $actor->organization_id !== (int) $organization->id ? '1' : '0',
        ]);
        $this->notifyAdmins($organization, $actor, 'lifted', '');
    }

    /** Bestehende Richtlinie oder eine neue ohne Regeln (der Notaus braucht eine Zeile). */
    private function policy(Organization $organization, User $actor): OrganizationLoginPolicy {
        $policy = OrganizationLoginPolicy::query()->withoutGlobalScopes()->where('organization_id', $organization->id)->first();
        if ($policy instanceof OrganizationLoginPolicy) {
            return $policy;
        }

        $policy = new OrganizationLoginPolicy;
        $policy->forceFill(['organization_id' => $organization->id, 'created_by' => $actor->id, 'updated_by' => $actor->id])->save();

        return $policy;
    }

    /**
     * Mitglieder der Organisation, die der Notaus trifft — ohne Plattformbetrieb
     * und ohne Portal-Konten.
     *
     * @return list<int>
     */
    private function memberIds(Organization $organization): array {
        return array_values(array_map('intval', User::query()
            ->where('organization_id', $organization->id)
            ->where('is_platform_admin', false)
            ->whereNull('customer_id')
            ->pluck('id')
            ->all()));
    }

    /**
     * Alle Org-Admins erfahren davon, unabhängig von Benachrichtigungsregeln:
     * ein Sicherheitsereignis wie die übrigen `security.*`-Meldungen.
     */
    private function notifyAdmins(Organization $organization, User $actor, string $action, string $reason): void {
        $admins = self::organizationAdmins((int) $organization->id);
        if ($admins->isEmpty()) {
            return;
        }

        $params = ['organization' => $organization->name, 'name' => $actor->name, 'reason' => $reason];
        $titleKey = 'login_policy.notification.lockdown_' . $action . '_title';
        $messageKey = 'login_policy.notification.lockdown_' . $action . '_message';

        try {
            Notification::send($admins, new GenericEventNotification(
                NotificationEvent::SecurityOrganizationLockdown,
                [
                    'title' => (string) __($titleKey, $params),
                    'title_key' => $titleKey,
                    'title_params' => $params,
                    'message' => (string) __($messageKey, $params),
                    'message_key' => $messageKey,
                    'message_params' => $params,
                    'url' => route('admin.login-policy.index'),
                ],
                ['database', 'mail'],
            ));
        } catch (\Throwable $e) {
            // Der Notaus selbst ist geschehen; eine gescheiterte Mail darf ihn nicht zurückdrehen.
            Log::warning('login_policy.lockdown_notify_failed', ['error' => $e->getMessage()]);
        }
    }

    /**
     * Aktive Org-Admins einer Organisation (Rolle im Team-Kontext der
     * Organisation) — Empfänger der Sicherheitsmeldungen der Richtlinie.
     *
     * @return Collection<int, User>
     */
    public static function organizationAdmins(int $organizationId): Collection {
        $registrar = app(PermissionRegistrar::class);
        $previous = $registrar->getPermissionsTeamId();
        $registrar->setPermissionsTeamId($organizationId);

        try {
            return User::query()
                ->where('organization_id', $organizationId)
                ->whereNull('customer_id')
                ->whereNull('deactivated_at')
                ->role(OrganizationRoles::matching([UserRole::Admin->value], $organizationId))
                ->get()
                ->toBase();
        } finally {
            $registrar->setPermissionsTeamId($previous);
        }
    }
}
