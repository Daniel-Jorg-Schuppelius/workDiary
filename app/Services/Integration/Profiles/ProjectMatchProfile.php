<?php
/*
 * Created on   : Tue Jun 30 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ProjectMatchProfile.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Integration\Profiles;

use App\Models\{Organization, Project};
use App\Services\Integration\Match\{AbstractMatchProfile, ExactField, FuzzyField, MatchStrategy};
use Illuminate\Database\Eloquent\Model;

/**
 * Abgleich-Profil für Projekte. Gedacht für den Dubletten-Finder, der die
 * Vergleiche zusätzlich auf denselben Kunden einschränkt — daher genügen hier
 * Namens-/Nummern-Strategien: gleiche Projektnummer (exact) → identischer Name
 * (likely) → ähnlicher Name (fuzzy).
 */
class ProjectMatchProfile extends AbstractMatchProfile {
    /** Schwelle für die Namens-Ähnlichkeit. */
    public const FUZZY_THRESHOLD = 0.86;

    public function targetType(): string {
        return Project::class;
    }

    public function strategies(): array {
        return [
            new ExactField('number', MatchStrategy::EXACT, 'number'),
            new ExactField('name', MatchStrategy::LIKELY, 'name'),
            new FuzzyField(['name'], self::FUZZY_THRESHOLD, 'name_similar'),
        ];
    }

    public function display(array $mapped): array {
        $title = (string) ($mapped['name'] ?? '');

        return [
            'title' => $title !== '' ? $title : (string) __('(ohne Namen)'),
            'subtitle' => ($mapped['number'] ?? null) !== null ? (string) $mapped['number'] : null,
        ];
    }

    public function create(Organization $organization, array $mapped): Model {
        $attributes = array_intersect_key($mapped, array_flip((new Project)->getFillable()));
        $attributes['organization_id'] = $organization->id;

        return Project::create($attributes);
    }
}
