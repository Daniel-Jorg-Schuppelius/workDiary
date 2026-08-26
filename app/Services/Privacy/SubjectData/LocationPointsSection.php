<?php
/*
 * Created on   : Mon Aug 24 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : LocationPointsSection.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Privacy\SubjectData;

use App\Models\Location\LocationPoint;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/**
 * Standortdaten des Mitarbeiters (Bewegungsspur der Standort-Zeiterfassung):
 * nur Zähler + Zeitraum — die Koordinaten selbst bleiben in der Auskunft
 * außen vor (at-rest verschlüsselt; Rohausgabe wäre ein eigener,
 * begründungspflichtiger Schritt).
 */
class LocationPointsSection extends AbstractSubjectSection {
    public function key(): string {
        return 'location_data';
    }

    public function title(): string {
        return __('Standortdaten (Übersicht)');
    }

    public function portable(): bool {
        return false;
    }

    public function build(Model $subject): array {
        $this->expect($subject, User::class);
        /** @var User $u */
        $u = $subject;

        return ['families' => [
            $this->family(
                'location_points',
                __('Standortpunkte (Bewegungsspur)'),
                LocationPoint::query()->withoutGlobalScopes()
                    ->where('organization_id', (int) $u->organization_id)
                    ->where('user_id', $u->id),
                'recorded_at',
            ),
        ]];
    }
}
