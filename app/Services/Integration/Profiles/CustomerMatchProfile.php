<?php
/*
 * Created on   : Mon Jun 29 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : CustomerMatchProfile.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Integration\Profiles;

use App\Models\{Customer, Organization};
use App\Services\Integration\Match\{AbstractMatchProfile, CompositeField, ExactField, FuzzyField, MatchStrategy};
use Illuminate\Database\Eloquent\Model;

/**
 * Abgleich-Profil für Kunden. Bildet die Match-Reihenfolge des bisherigen
 * Lexoffice-/Toggl-Abgleichs ab: USt-IdNr. → Lexoffice-Nr. (exact) → E-Mail →
 * Firma+PLZ (likely) → Name/Firma-Ähnlichkeit (fuzzy).
 */
class CustomerMatchProfile extends AbstractMatchProfile {
    /** Schwelle für die Namens-/Firmen-Ähnlichkeit. */
    public const FUZZY_THRESHOLD = 0.86;

    public function targetType(): string {
        return Customer::class;
    }

    public function strategies(): array {
        return [
            new ExactField('vat_id', MatchStrategy::EXACT, 'vat_id'),
            new ExactField('lexoffice_contact_number', MatchStrategy::EXACT, 'lexoffice_contact_number'),
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
        $attributes = array_intersect_key($mapped, array_flip((new Customer)->getFillable()));
        $attributes['organization_id'] = $organization->id;
        if (! array_key_exists('billable', $attributes)) {
            $attributes['billable'] = true;
        }

        return Customer::create($attributes);
    }
}
