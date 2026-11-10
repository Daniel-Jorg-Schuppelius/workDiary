<?php
/*
 * Created on   : Mon Nov 09 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : MatchesIdentifiers.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Models\Concerns;

use App\Casts\IdentifierCast;
use Illuminate\Database\Eloquent\Builder;
use LogicException;

/**
 * Suche über Kennungsspalten (USt-IdNr., GTIN; MVP-1527). Der Kennungs-Cast
 * speichert gültige Werte kanonisch (MVP-1512), Eingaben kommen aber mit
 * Leerzeichen oder in Kleinbuchstaben. Verglichen wird deshalb mit der
 * Speicherform des Casts und zusätzlich mit der getrimmten Eingabe: diese
 * trifft ungültige Werte und Altbestand aus der Zeit vor den Casts.
 */
trait MatchesIdentifiers {
    /**
     * @param  Builder<static>  $query
     * @param  string|list<string>  $values
     */
    public function scopeWhereIdentifier(Builder $query, string $column, string|array $values): void {
        $cast = $this->hasCast($column) && $this->isClassCastable($column) ? $this->resolveCasterClass($column) : null;
        if (! $cast instanceof IdentifierCast) {
            throw new LogicException(static::class . "::{$column} ist keine Kennungsspalte.");
        }

        $candidates = [];
        foreach ((array) $values as $value) {
            $raw = trim($value);
            if ($raw !== '') {
                $candidates[] = $cast->storedForm($raw);
                $candidates[] = $raw;
            }
        }

        $query->whereIn($query->qualifyColumn($column), array_values(array_unique($candidates)));
    }
}
