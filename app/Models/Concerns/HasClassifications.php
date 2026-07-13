<?php
/*
 * Created on   : Sun Jul 12 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : HasClassifications.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Models\Concerns;

use App\Models\Classification;
use Illuminate\Database\Eloquent\Relations\MorphToMany;

/**
 * Polymorphe Klassifikations-Zuordnung (Muster {@see HasTags}/`taggables`).
 *
 * A13 (MVP-049): Der Import-Wizard kann Quellwerte einer Tag-/Kategorie-
 * Spalte auf Klassifikationen des Katalogs (Plattform-Default oder
 * Org-Override) mappen und hängt sie hierüber ans Zielobjekt. Nur Modelle
 * mit diesem Trait bieten Klassifikations-Ziele im Wertmapping an
 * ({@see \App\Enums\Import\ImportEntity::supportsClassifications()}).
 */
trait HasClassifications {
    /** @return MorphToMany<Classification, $this> */
    public function classifications(): MorphToMany {
        return $this->morphToMany(Classification::class, 'classifiable');
    }
}
