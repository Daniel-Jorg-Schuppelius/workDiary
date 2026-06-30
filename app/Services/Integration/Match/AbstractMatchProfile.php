<?php
/*
 * Created on   : Mon Jun 29 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : AbstractMatchProfile.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Integration\Match;

use App\Models\Organization;
use Illuminate\Database\Eloquent\{Builder, Model};

/**
 * Basis für {@see MatchProfile}-Implementierungen: liefert Standard-Kandidaten-
 * Query (org-gescopt, archivierte ausgeschlossen sofern Spalte vorhanden) und
 * generisches Feld-Extrahieren aus Modellen anhand der Strategie-Felder.
 */
abstract class AbstractMatchProfile implements MatchProfile {
    public function candidates(Organization $organization): Builder {
        $modelClass = $this->targetType();
        /** @var Builder<Model> $query */
        $query = $modelClass::query()
            ->withoutGlobalScopes()
            ->where('organization_id', $organization->id);

        $model = new $modelClass;
        if (in_array('archived_at', $model->getFillable(), true)) {
            $query->whereNull('archived_at');
        }

        return $query;
    }

    public function extract(Model $model): array {
        $out = [];
        foreach ($this->matchFields() as $field) {
            $out[$field] = $model->getAttribute($field);
        }

        return $out;
    }

    /**
     * Vereinigung aller von den Strategien gelesenen Feldnamen.
     *
     * @return list<string>
     */
    protected function matchFields(): array {
        $fields = [];
        foreach ($this->strategies() as $strategy) {
            foreach ($strategy->fields() as $field) {
                $fields[$field] = true;
            }
        }

        return array_keys($fields);
    }
}
