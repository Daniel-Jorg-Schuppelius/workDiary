<?php
/*
 * Created on   : Wed Nov 11 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : QualificationMatrixBuilder.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Reporting;

use App\Models\Hr\Qualification;
use App\Models\Platform\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\{Builder, Collection};

/**
 * Rechenweg der Qualifikationsmatrix: Personen × aktive Qualifikationen mit
 * Stand gültig, läuft ab (30 Tage) oder abgelaufen. Bericht und
 * Leitungssicht (MVP-1617) rechnen hier.
 *
 * @phpstan-type MatrixCell array{valid_from: ?string, valid_until: ?string, state: string}
 */
final class QualificationMatrixBuilder {
    public const int EXPIRY_WARN_DAYS = 30;

    /** @return Collection<int, Qualification> Katalog ohne inaktive Qualifikationen */
    public function qualifications(): Collection {
        /** @var Collection<int, Qualification> $qualifications */
        $qualifications = Qualification::query()->active()->orderBy('name')->get(['id', 'name', 'abbreviation', 'is_active']);

        return $qualifications;
    }

    /**
     * Personen der Organisation mit mindestens einer aktiven Qualifikation.
     *
     * @param  (callable(Builder<User>): mixed)|null  $constrain  grenzt die Personen ein (eigene Zeile, Filter)
     * @return Collection<int, User>
     */
    public function users(?callable $constrain = null): Collection {
        $activeOnly = static fn ($q) => $q->where('qualifications.is_active', true);
        $query = User::inCurrentOrganization()->whereHas('qualifications', $activeOnly)->orderBy('name');
        if ($constrain !== null) {
            $constrain($query);
        }

        /** @var Collection<int, User> $users */
        $users = $query->with(['qualifications' => fn ($q) => $activeOnly($q)->select(['qualifications.id', 'qualifications.name'])])->get(['id', 'name']);

        return $users;
    }

    /**
     * @param  Collection<int, User>  $users  aus {@see users()}
     * @return array{matrix: array<int, array<int, MatrixCell>>, byQualification: array<int, array{valid: int, expiring: int, expired: int}>, assignments: int, expiring: int, expired: int}
     */
    public function matrix(Collection $users, Carbon $today): array {
        $today = $today->copy()->startOfDay();
        $warnDate = $today->copy()->addDays(self::EXPIRY_WARN_DAYS);
        $matrix = [];
        $byQualification = [];
        $assignments = 0;
        $expiring = 0;
        $expired = 0;
        foreach ($users as $u) {
            $uid = (int) $u->id;
            $matrix[$uid] = [];
            foreach ($u->qualifications as $q) {
                $assignments++;
                $pivot = $q->getRelationValue('pivot');
                $validUntil = $pivot !== null ? $pivot->getAttribute('valid_until') : null;
                $validFrom = $pivot !== null ? $pivot->getAttribute('valid_from') : null;
                $state = 'valid';
                if ($validUntil !== null) {
                    $end = Carbon::parse((string) $validUntil)->startOfDay();
                    if ($end->lt($today)) {
                        $state = 'expired';
                        $expired++;
                    } elseif ($end->lte($warnDate)) {
                        $state = 'expiring';
                        $expiring++;
                    }
                }
                $matrix[$uid][(int) $q->id] = [
                    'valid_from' => $validFrom !== null ? Carbon::parse((string) $validFrom)->toDateString() : null,
                    'valid_until' => $validUntil !== null ? Carbon::parse((string) $validUntil)->toDateString() : null,
                    'state' => $state,
                ];
                $byQualification[(int) $q->id] ??= ['valid' => 0, 'expiring' => 0, 'expired' => 0];
                $byQualification[(int) $q->id][$state]++;
            }
        }

        return ['matrix' => $matrix, 'byQualification' => $byQualification, 'assignments' => $assignments, 'expiring' => $expiring, 'expired' => $expired];
    }
}
