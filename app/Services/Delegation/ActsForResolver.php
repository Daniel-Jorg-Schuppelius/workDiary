<?php
/*
 * Created on   : Thu Nov 12 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ActsForResolver.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Delegation;

use App\Models\Delegation\WorkDelegation;
use App\Models\Platform\User;
use App\Models\Scopes\OrganizationScope;
use App\Support\Tz;
use Carbon\{CarbonImmutable, CarbonInterface};
use Illuminate\Container\Attributes\Scoped;
use Illuminate\Database\Eloquent\Collection;

/**
 * Für wen handelt jemand gerade (Feature 202, MVP-1647)? Liefert die Personen,
 * die ein Nutzer am Tag vertritt, und umgekehrt seine Vertretungen.
 *
 * Wirkt nur dort, wo die **Zuweisung** entscheidet („zugewiesen an X“):
 * Arbeitsvorrat, Benachrichtigungen an die zugewiesene Person, ab MVP-1648
 * Policies und „Meine“-Sichten (`whereIn(assigned, [$user->id, ...principalIds($user)])`).
 * Rechteprüfungen (`can('…')`) prüfen immer die handelnde Person — eine
 * Vertretung verleiht keine Rechte. Es gibt keine Kette: vertreten wird nur,
 * wer selbst eingetragen ist, nie dessen Vertretene. Nur innerhalb der
 * Organisation des Nutzers; deaktivierte Konten zählen nicht.
 *
 * Je Anfrage (Scoped) gemerkt; jede Änderung an einer Vertretung verwirft den
 * Speicher ({@see WorkDelegation::booted()}).
 */
#[Scoped]
final class ActsForResolver {
    /** @var array<string, Collection<int, WorkDelegation>> Nutzer:Tag → wirksame Vertretungen */
    private array $memo = [];

    /**
     * Personen, die $user am Tag (Vorgabe: heute) vertritt.
     *
     * @return list<User>
     */
    public function for(User $user, ?CarbonInterface $on = null): array {
        $out = [];
        foreach ($this->involving($user, $on) as $delegation) {
            $principal = $delegation->represented;
            if ((int) $delegation->deputy_user_id === (int) $user->id && $principal instanceof User && ! $principal->isDeactivated()) {
                $out[(int) $principal->id] = $principal;
            }
        }

        return array_values($out);
    }

    /**
     * IDs der Personen, die $user am Tag vertritt — Anschluss für Policies und
     * „Meine“-Sichten (MVP-1648), ohne die eigene ID.
     *
     * @return list<int>
     */
    public function principalIds(User $user, ?CarbonInterface $on = null): array {
        $ids = array_map(static fn (User $principal): int => (int) $principal->id, $this->for($user, $on));
        sort($ids);

        return $ids;
    }

    /**
     * Darf $user handeln, was die Zuweisung an $assigneeId erlaubt — weil es
     * die eigene ist oder weil $user die zugewiesene Person am Tag vertritt
     * (MVP-1648)? Beantwortet nur die Zuweisung; Rechte (`can(…)`) prüft der
     * Aufrufer weiter an der handelnden Person.
     */
    public function actsFor(User $user, ?int $assigneeId, ?CarbonInterface $on = null): bool {
        if ($assigneeId === null || $assigneeId <= 0) {
            return false;
        }
        if ($assigneeId === (int) $user->id) {
            return true;
        }

        return in_array($assigneeId, $this->principalIds($user, $on), true);
    }

    /**
     * Eigene ID und die der heute Vertretenen — für „Meine“-Sichten
     * (`whereIn('assigned_user_id', assigneeIds($user))`, MVP-1648).
     *
     * @return list<int>
     */
    public function assigneeIds(User $user, ?CarbonInterface $on = null): array {
        return array_values(array_unique([(int) $user->id, ...$this->principalIds($user, $on)]));
    }

    /**
     * Wer $user am Tag vertritt (aktive Konten).
     *
     * @return list<User>
     */
    public function deputiesOf(User $user, ?CarbonInterface $on = null): array {
        $out = [];
        foreach ($this->involving($user, $on) as $delegation) {
            $deputy = $delegation->deputy;
            if ((int) $delegation->represented_user_id === (int) $user->id && $deputy instanceof User && ! $deputy->isDeactivated()) {
                $out[(int) $deputy->id] = $deputy;
            }
        }

        return array_values($out);
    }

    /**
     * Am Tag wirksame Vertretungen, an denen $user beteiligt ist — vertreten
     * oder vertretend —, mit beiden Personen geladen. Eine Abfrage je Nutzer und Tag.
     *
     * @return Collection<int, WorkDelegation>
     */
    public function involving(User $user, ?CarbonInterface $on = null): Collection {
        $organizationId = (int) $user->organization_id;
        if ($organizationId <= 0 || (int) $user->id <= 0) {
            return new Collection;
        }
        $day = $on !== null ? CarbonImmutable::instance($on)->startOfDay() : CarbonImmutable::now(Tz::current())->startOfDay();
        $key = $user->id . ':' . $day->format('Y-m-d');

        return $this->memo[$key] ??= WorkDelegation::query()
            ->withoutGlobalScope(OrganizationScope::class)
            ->where('organization_id', $organizationId)
            ->where(static fn ($q) => $q->where('represented_user_id', $user->id)->orWhere('deputy_user_id', $user->id))
            ->activeOn($day)
            // Volle Personen: der Arbeitsvorrat fragt die Quellen im Namen der vertretenen Person (Policies, Rollen).
            ->with(['represented', 'deputy'])
            ->orderBy('valid_from')
            ->orderBy('id')
            ->get();
    }

    public function flush(): void {
        $this->memo = [];
    }
}
