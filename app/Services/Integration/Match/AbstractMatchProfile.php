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
 *
 * @template TModel of Model
 */
abstract class AbstractMatchProfile implements MatchProfile {
    /** @return class-string<TModel> */
    abstract public function targetType(): string;

    /**
     * Frische Basis-Query des Zielmodells — konkret je Profil, weil eine
     * Query über class-string den Modell-Generic zu Model kollabieren lässt.
     *
     * @return Builder<TModel>
     */
    abstract protected function newCandidateQuery(): Builder;

    /** @return Builder<TModel> */
    public function candidates(Organization $organization): Builder {
        $query = $this->newCandidateQuery();
        $query->withoutGlobalScopes()->where('organization_id', $organization->id);

        if (in_array('archived_at', $query->getModel()->getFillable(), true)) {
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
