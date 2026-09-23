<?php
/*
 * Created on   : Mon Jun 29 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : CustomerDuplicateFinder.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services;

use App\Models\{Customer, CustomerMergeDismissal, Organization};
use App\Services\Integration\Match\{EntityMatcher, MatchProfile};
use App\Services\Integration\Profiles\CustomerMatchProfile;
use Illuminate\Database\Eloquent\{Collection as EloquentCollection, Model};

/**
 * Findet Dubletten-Kandidaten unter den Kunden einer Organisation. Nutzt das
 * gemeinsame {@see CustomerMatchProfile} + {@see EntityMatcher} (keine eigene
 * Match-Logik mehr); Paar-Schleife/Dismissal-Filter siehe
 * {@see AbstractDuplicateFinder}.
 *
 * @extends AbstractDuplicateFinder<Customer>
 */
class CustomerDuplicateFinder extends AbstractDuplicateFinder {
    public function __construct(
        EntityMatcher $matcher,
        private readonly CustomerMatchProfile $profile,
    ) {
        parent::__construct($matcher);
    }

    protected function profile(): MatchProfile {
        return $this->profile;
    }

    protected function fetchCandidates(Organization $organization): EloquentCollection {
        return $this->profile->candidates($organization)->withCount('projects')->get();
    }

    /**
     * Ziel-Heuristik: Lexoffice-Anbindung > mehr Projekte > kleinere (ältere) ID.
     */
    protected function score(Model $model): array {
        $hasLex = trim((string) $model->lexoffice_contact_number) !== '' ? 1 : 0;

        return [$hasLex, (int) ($model->projects_count ?? 0), -((int) $model->id)];
    }

    protected function dismissalModel(): string {
        return CustomerMergeDismissal::class;
    }

    protected function dismissalKeyColumns(): array {
        return ['customer_low_id', 'customer_high_id'];
    }
}
