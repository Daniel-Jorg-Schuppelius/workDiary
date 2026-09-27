<?php
/*
 * Created on   : Sun Sep 27 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : DamageCaseSubject.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Models\Contracts;

use App\Enums\Damage\DamageKind;
use App\Models\Damage\DamageCase;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/** Träger von Schadensfällen (MVP-919); Umsetzung über `HasDamageCases`. */
interface DamageCaseSubject {
    /** @return MorphMany<DamageCase, covariant \Illuminate\Database\Eloquent\Model> */
    public function damageCases(): MorphMany;

    /** Anzeigename des Trägers in Liste und Akte. */
    public function damageSubjectLabel(): string;

    /** Detailseite des Trägers, falls vorhanden. */
    public function damageSubjectUrl(): ?string;

    /** Vorbelegung der Schadensart beim Anlegen. */
    public function damageDefaultKind(): DamageKind;
}
