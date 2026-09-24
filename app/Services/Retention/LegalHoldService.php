<?php
/*
 * Created on   : Thu Sep 17 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : LegalHoldService.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Retention;

use App\Exceptions\Privacy\LegalHoldException;
use App\Models\Customer\Customer;
use App\Models\Platform\{Organization, User};
use App\Models\Privacy\LegalHold;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use InvalidArgumentException;

/**
 * Legal Hold (MVP-801, Feature 130) — einzige Stelle, die Vermerke setzt,
 * aufhebt und auswertet.
 *
 * Ein Vermerk hängt an einer Person oder einem Kunden. Andere Datensätze gelten
 * als gesperrt, wenn sie über `user_id` bzw. `customer_id` auf eine gesperrte
 * Person oder einen gesperrten Kunden zeigen — der Weg, über den die
 * Aufbewahrungsbereiche ihre Betroffenen führen. Zuständige (`responsible_user_id`,
 * `assigned_user_id`) sind keine Betroffenen und sperren nichts.
 */
class LegalHoldService {
    public function place(Model $holdable, string $reason, ?string $reference, User $actor): LegalHold {
        $this->assertHoldable($holdable);

        $hold = LegalHold::query()->create([
            'organization_id' => (int) $holdable->getAttribute('organization_id'),
            'holdable_type' => $holdable->getMorphClass(),
            'holdable_id' => (int) $holdable->getKey(),
            'reason' => $reason,
            'reference' => $reference,
            'placed_by' => $actor->id,
            'placed_at' => Carbon::now(),
        ]);
        $this->auditOn($holdable, 'legal_hold.placed', $hold);

        return $hold;
    }

    public function release(LegalHold $hold, string $reason, User $actor): LegalHold {
        if (! $hold->isActive()) {
            return $hold;
        }

        $hold->forceFill([
            'released_by' => $actor->id,
            'released_at' => Carbon::now(),
            'release_reason' => $reason,
        ])->save();

        $holdable = $hold->holdable()->withoutGlobalScopes()->first();
        if ($holdable instanceof Model) {
            $this->auditOn($holdable, 'legal_hold.released', $hold);
        }

        return $hold;
    }

    /** Aktiver Vermerk, der den Datensatz sperrt — direkt oder über Person/Kunde. */
    public function activeHoldFor(Model $subject): ?LegalHold {
        foreach ($this->holdablesOf($subject) as [$type, $id]) {
            $hold = LegalHold::query()->withoutGlobalScopes()
                ->where('holdable_type', $type)
                ->where('holdable_id', $id)
                ->whereNull('released_at')
                ->orderBy('placed_at')
                ->first();
            if ($hold instanceof LegalHold) {
                return $hold;
            }
        }

        return null;
    }

    /** @throws LegalHoldException */
    public function assertNotHeld(Model $subject): void {
        $hold = $this->activeHoldFor($subject);
        if ($hold !== null) {
            throw new LegalHoldException($hold);
        }
    }

    /**
     * IDs gesperrter Personen über alle Organisationen — für Aufräumläufe ohne
     * Organisationskontext (z. B. Standort-Rohpunkte).
     *
     * @return list<int>
     */
    public function heldUserIds(): array {
        $ids = LegalHold::query()->withoutGlobalScopes()
            ->where('holdable_type', (new User)->getMorphClass())
            ->whereNull('released_at')
            ->pluck('holdable_id')
            ->all();

        return array_values(array_unique(array_map('intval', $ids)));
    }

    public function organizationHasActiveHolds(Organization $organization): bool {
        return LegalHold::query()->withoutGlobalScopes()
            ->where('organization_id', $organization->id)
            ->whereNull('released_at')
            ->exists();
    }

    /** @return list<array{0: string, 1: int}> */
    private function holdablesOf(Model $subject): array {
        if ($subject instanceof User || $subject instanceof Customer) {
            return [[$subject->getMorphClass(), (int) $subject->getKey()]];
        }

        $pairs = [];
        $userId = $subject->getAttribute('user_id');
        if (is_numeric($userId)) {
            $pairs[] = [(new User)->getMorphClass(), (int) $userId];
        }
        $customerId = $subject->getAttribute('customer_id');
        if (is_numeric($customerId)) {
            $pairs[] = [(new Customer)->getMorphClass(), (int) $customerId];
        }

        return $pairs;
    }

    private function assertHoldable(Model $holdable): void {
        if (! $holdable instanceof User && ! $holdable instanceof Customer) {
            throw new InvalidArgumentException('Legal Hold gilt nur für Personen und Kunden.');
        }
        if ($holdable->getAttribute('organization_id') === null) {
            throw new InvalidArgumentException('Legal Hold braucht eine Organisation.');
        }
    }

    /** Auch an der Person bzw. dem Kunden protokollieren — dort sucht man zuerst. */
    private function auditOn(Model $holdable, string $event, LegalHold $hold): void {
        if (method_exists($holdable, 'audit')) {
            $holdable->audit($event, ['legal_hold_id' => $hold->id, 'reference' => $hold->reference]);
        }
    }
}
