<?php
/*
 * Created on   : Tue Nov 10 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : StepQualificationCheck.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Procedure;

use App\Models\Hr\UserQualification;
use App\Models\Platform\User;
use App\Support\Query\DateRange;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

/**
 * Hält eine Person am Stichtag eine gültige Qualifikation mit dem geforderten
 * Code (MVP-1568)? Der Code ist das Kürzel der Qualifikation, ersatzweise ihr
 * Name. Gültig heißt wie in der Schichtplanung: Beginn nicht in der Zukunft,
 * Ablauf nicht in der Vergangenheit, offene Grenzen gelten unbefristet.
 */
final class StepQualificationCheck {
    public function holds(User $user, string $code, int $organizationId, ?CarbonInterface $on = null): bool {
        $code = mb_strtolower(trim($code));
        if ($code === '') {
            return true;
        }
        $day = $on ?? Carbon::today();

        return UserQualification::query()
            ->where('user_id', $user->id)
            ->where(fn (Builder $q) => $q->whereNull('valid_from')->orWhere('valid_from', '<', DateRange::dayAfter($day)))
            ->where(fn (Builder $q) => $q->whereNull('valid_until')->orWhere('valid_until', '>=', DateRange::day($day)))
            ->whereHas('qualification', fn (Builder $q) => $q
                ->withoutGlobalScopes()
                ->where('organization_id', $organizationId)
                ->where(fn (Builder $w) => $w->whereRaw('LOWER(abbreviation) = ?', [$code])->orWhereRaw('LOWER(name) = ?', [$code])))
            ->exists();
    }
}
