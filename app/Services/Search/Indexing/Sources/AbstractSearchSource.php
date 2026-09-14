<?php
/*
 * Created on   : Mon Sep 14 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : AbstractSearchSource.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Search\Indexing\Sources;

use App\Models\Scopes\OrganizationScope;
use CommonToolkit\Helper\Data\StringHelper;
use Illuminate\Database\Eloquent\{Builder, Model};

abstract class AbstractSearchSource implements SearchSource {
    public function query(?int $organizationId): Builder {
        $class = $this->type()->modelClass();
        // Nur der Organisations-Scope fällt weg; SoftDeletes bleibt aktiv.
        $query = $class::query()->withoutGlobalScope(OrganizationScope::class);
        if ($organizationId !== null) {
            $query->where($query->getModel()->qualifyColumn('organization_id'), $organizationId);
        }

        return $this->scope($query);
    }

    /**
     * Einschränkung auf indizierbare Zeilen und Eager Loads.
     *
     * @param  Builder<Model>  $query
     * @return Builder<Model>
     */
    protected function scope(Builder $query): Builder {
        return $query;
    }

    protected static function organizationOf(Model $model): ?int {
        $id = $model->getAttribute('organization_id');

        return $id === null ? null : (int) $id;
    }

    protected static function intOrNull(mixed $value): ?int {
        return $value === null ? null : (int) $value;
    }

    /** Erste nicht-leere Zeile als Anzeige-Titel. */
    protected static function firstLine(?string $text): string {
        foreach (preg_split('/\R/u', trim((string) $text)) ?: [] as $line) {
            $line = trim($line);
            if ($line !== '') {
                return StringHelper::truncate($line, 255, '…');
            }
        }

        return '';
    }

    /** HTML (Ticket-Mails) als lesbarer Text. */
    protected static function plain(?string $html): string {
        if ($html === null || $html === '') {
            return '';
        }

        return StringHelper::normalizeWhitespace(StringHelper::htmlEntitiesToText(strip_tags($html)));
    }

    /**
     * Nicht-leere Texte in Reihenfolge.
     *
     * @param  mixed  ...$values
     */
    protected static function join(string $separator, ...$values): string {
        $parts = [];
        foreach ($values as $value) {
            if (is_string($value) && trim($value) !== '') {
                $parts[] = trim($value);
            }
        }

        return implode($separator, $parts);
    }

    /**
     * String-Attribute einer Modell-Sammlung (Tag-Namen, Kommentartexte);
     * leere und Nicht-Strings fallen weg, wie später im Indexer.
     *
     * @param  iterable<Model>  $models
     * @return list<string>
     */
    protected static function strings(iterable $models, string $attribute): array {
        $values = [];
        foreach ($models as $model) {
            $value = $model->getAttribute($attribute);
            if (is_string($value) && $value !== '') {
                $values[] = $value;
            }
        }

        return $values;
    }
}
