<?php
/*
 * Created on   : Thu Jul 30 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : SecurityCrisisEscalator.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Security;

use App\Models\Crisis\CrisisCase;
use App\Models\{Organization, User};
use App\Services\Crisis\CrisisAlertService;
use Illuminate\Support\Facades\Log;

/**
 * Massenangriff → Krisenmodul (Feature 097, MVP-449): eine Schwellwert-Regel
 * mit `crisis => true` erzeugt statt einer normalen Admin-Notification einen
 * echten Krisenfall (Feature 070) mit Stabsalarmierung und
 * Quittierungspflicht — Ruhezeiten werden dabei bewusst überstimmt (D7).
 *
 * Die Entwarnung folgt derselben Zustandswechsel-Logik wie in Feature 096:
 * fällt die Rate unter das Limit, wird der laufende Fall auf `all_clear`
 * gesetzt (auditiert), statt einen zweiten Fall zu eröffnen.
 */
class SecurityCrisisEscalator {
    /** Marker im `trigger_source`, über den die Entwarnung den Fall findet. */
    public const TRIGGER_PREFIX = 'security:';

    /**
     * @param  array{key: string, event: string, scope: string, window_minutes: int, limit: int, crisis?: bool}  $rule
     * @return CrisisCase|null  `null` = keine Betreiber-Org/kein Admin vorhanden
     */
    public function raise(array $rule, int $count): ?CrisisCase {
        $actor = $this->actor();
        if (! $actor instanceof User) {
            return null;
        }
        $organization = $actor->organization ?? Organization::query()->orderBy('id')->first();
        if (! $organization instanceof Organization) {
            return null;
        }

        $trigger = self::TRIGGER_PREFIX . $rule['key'];
        $existing = $this->openCase($organization, $trigger);
        if ($existing instanceof CrisisCase) {
            return $existing; // idempotent — ein Fall je Regel
        }

        $params = [
            'event' => $rule['event'],
            'count' => $count,
            'window' => (int) $rule['window_minutes'],
            'limit' => (int) $rule['limit'],
        ];

        try {
            $case = CrisisCase::query()->create([
                'organization_id' => $organization->id,
                'title' => (string) __('security.crisis.mass_attack_title', $params),
                'category' => 'security',
                'severity' => 'critical',
                'status' => 'activated',
                'trigger_source' => $trigger,
                'description' => (string) __('security.crisis.mass_attack_description', $params),
                'activated_at' => now(),
                'responsible_user_id' => $actor->id,
                'created_by' => $actor->id,
            ]);
            $case->audit('crisis.reported', ['trigger' => $trigger, 'count' => $count]);
            app(CrisisAlertService::class)->alert($case, $actor);

            return $case;
        } catch (\Throwable $e) {
            Log::error('security.crisis_escalation_failed', ['rule' => $rule['key'], 'error' => $e->getMessage()]);

            return null;
        }
    }

    /**
     * Entwarnung: laufenden Fall der Regel auf `all_clear` setzen.
     *
     * @param  array{key: string, event: string, scope: string, window_minutes: int, limit: int, crisis?: bool}  $rule
     */
    public function allClear(array $rule, int $count): bool {
        $actor = $this->actor();
        $organization = ($actor instanceof User ? $actor->organization : null)
            ?? Organization::query()->orderBy('id')->first();
        if (! $organization instanceof Organization) {
            return false;
        }

        $case = $this->openCase($organization, self::TRIGGER_PREFIX . $rule['key']);
        if (! $case instanceof CrisisCase) {
            return false;
        }

        $case->update(['status' => 'all_clear', 'all_clear_at' => now()]);
        $case->audit('crisis.all_clear', ['trigger' => 'security.rate_normalized', 'count' => $count]);

        return true;
    }

    private function openCase(Organization $organization, string $trigger): ?CrisisCase {
        return CrisisCase::query()
            ->withoutGlobalScopes()
            ->where('organization_id', $organization->id)
            ->where('trigger_source', $trigger)
            ->whereIn('status', CrisisCase::ACTIVE_STATUSES)
            ->latest('id')
            ->first();
    }

    private function actor(): ?User {
        return User::query()->where('is_platform_admin', true)->orderBy('id')->first();
    }
}
