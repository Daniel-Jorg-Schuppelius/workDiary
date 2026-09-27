<?php
/*
 * Created on   : Sun Sep 27 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : HasDamageCases.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Models\Concerns;

use App\Models\Damage\DamageCase;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/** Schadensfälle am Träger (MVP-919), neueste zuerst. */
trait HasDamageCases {
    /** @return MorphMany<DamageCase, $this> */
    public function damageCases(): MorphMany {
        return $this->morphMany(DamageCase::class, 'subject')->latest('id');
    }
}
