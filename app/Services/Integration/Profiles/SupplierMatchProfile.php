<?php
/*
 * Created on   : Tue Jun 30 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : SupplierMatchProfile.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Integration\Profiles;

use App\Models\{Organization, Supplier};
use App\Services\Integration\Match\{AbstractMatchProfile, CompositeField, ExactField, FuzzyField, MatchStrategy};
use Illuminate\Database\Eloquent\{Builder, Model};

/**
 * Abgleich-Profil für Lieferanten: USt-IdNr. → Lieferantennummer (exact) →
 * E-Mail → Firma+PLZ (likely) → Name/Firma-Ähnlichkeit (fuzzy). Spiegelbild des
 * {@see CustomerMatchProfile} für die Supplier-Entität.
 *
 * @extends AbstractMatchProfile<Supplier>
 */
class SupplierMatchProfile extends AbstractMatchProfile {
    public const FUZZY_THRESHOLD = 0.86;

    public function targetType(): string {
        return Supplier::class;
    }

    protected function newCandidateQuery(): Builder {
        return Supplier::query();
    }

    public function strategies(): array {
        return [
            new ExactField('vat_id', MatchStrategy::EXACT, 'vat_id'),
            new ExactField('vendor_number', MatchStrategy::EXACT, 'vendor_number'),
            new ExactField('email', MatchStrategy::LIKELY, 'email'),
            new CompositeField(['company', 'address_zip'], MatchStrategy::LIKELY, 'company_zip'),
            new FuzzyField(['name', 'company'], self::FUZZY_THRESHOLD, 'name'),
        ];
    }

    public function display(array $mapped): array {
        $title = (string) ($mapped['name'] ?? $mapped['company'] ?? '');
        $subtitleParts = array_filter([
            (string) ($mapped['company'] ?? ''),
            (string) ($mapped['email'] ?? ''),
            (string) ($mapped['vat_id'] ?? ''),
        ], static fn(string $v): bool => $v !== '' && $v !== $title);

        return [
            'title' => $title !== '' ? $title : (string) __('(ohne Namen)'),
            'subtitle' => $subtitleParts !== [] ? implode(' · ', $subtitleParts) : null,
        ];
    }

    public function create(Organization $organization, array $mapped): Model {
        $attributes = array_intersect_key($mapped, array_flip((new Supplier)->getFillable()));
        $attributes['organization_id'] = $organization->id;

        return Supplier::create($attributes);
    }
}
