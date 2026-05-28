<?php
/*
 * Created on   : Wed May 27 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : HasSqid.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Models\Concerns;

use App\Services\SqidEncoder;
use Illuminate\Database\Eloquent\Model;

/**
 * Wandelt URL-Routing eines Modells von der numerischen PK auf eine opake
 * Sqid um. Routen können statt `customers/{customer}` mit `customer:id`
 * weiterhin nur das Modell binden — das Routing-Segment ist dann der Sqid.
 *
 * Eingehende numerische IDs werden NICHT mehr akzeptiert (harter Cut):
 * `resolveRouteBinding` liefert `null`, was Laravel als 404 behandelt.
 *
 * @property int $id
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static> query()
 */
trait HasSqid {
    /**
     * Sqid wird virtuell als Route-Key verwendet.
     */
    public function getRouteKeyName(): string {
        return 'sqid';
    }

    /**
     * Liefert die Sqid für URL-Generierung (`route(..., $model)`).
     */
    public function getRouteKey(): string {
        return $this->sqidEncoder()->encode(static::class, (int) $this->getKey());
    }

    /**
     * Accessor für `$model->sqid` (Blade, JSON-Serialisierung).
     */
    public function getSqidAttribute(): string {
        return $this->getRouteKey();
    }

    /**
     * Resolves a route binding by decoding the Sqid and loading the model
     * via its real primary key. Returns null on invalid / cross-model Sqids
     * — Laravel then emits a 404.
     *
     * @param  string  $value
     * @param  string|null  $field
     */
    public function resolveRouteBinding($value, $field = null): ?Model {
        // Explizit angefordertes Feld (z.B. `Route::bind('x:slug', ...)`)
        // weiterreichen — überschreibt Sqid-Decoding.
        if ($field !== null && $field !== 'sqid') {
            return parent::resolveRouteBinding($value, $field);
        }

        $id = $this->sqidEncoder()->decode(static::class, (string) $value);
        if ($id === null) {
            return null;
        }

        return static::query()->whereKey($id)->first();
    }

    /**
     * Resolves a child route binding (nested resources) by decoding the
     * Sqid relative to the parent relation.
     *
     * @param  string  $childType
     * @param  string  $value
     * @param  string|null  $field
     */
    public function resolveChildRouteBinding($childType, $value, $field = null): ?Model {
        if ($field !== null && $field !== 'sqid') {
            return parent::resolveChildRouteBinding($childType, $value, $field);
        }

        $relation = $this->resolveChildRouteBindingQuery($childType, $value, $field);
        $related = $relation->getRelated();

        if (! method_exists($related, 'getSqidEncoderForRouting')) {
            // Kind-Modell nutzt kein HasSqid → Standardverhalten.
            return parent::resolveChildRouteBinding($childType, $value, $field);
        }

        $id = app(SqidEncoder::class)->decode($related::class, (string) $value);
        if ($id === null) {
            return null;
        }

        return $relation->whereKey($id)->first();
    }

    /**
     * Marker für Trait-Detection in `resolveChildRouteBinding` ohne
     * Reflection. Bewusst leer.
     */
    public function getSqidEncoderForRouting(): SqidEncoder {
        return $this->sqidEncoder();
    }

    private function sqidEncoder(): SqidEncoder {
        return app(SqidEncoder::class);
    }
}
