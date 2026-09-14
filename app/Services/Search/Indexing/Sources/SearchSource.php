<?php
/*
 * Created on   : Mon Sep 14 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : SearchSource.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Search\Indexing\Sources;

use App\Enums\Search\SearchSourceType;
use App\Services\Search\Indexing\{SearchContext, SearchDocumentData};
use Illuminate\Database\Eloquent\{Builder, Model};

/** Eine Quelle des Tätigkeitsindex (Feature 153, MVP-770). */
interface SearchSource {
    public function type(): SearchSourceType;

    /**
     * Alle indizierbaren Zeilen samt Eager Loads — ohne Organisations-Scope
     * (Konsole/Queue), mit `null` über alle Mandanten.
     *
     * @return Builder<Model>
     */
    public function query(?int $organizationId): Builder;

    /** Dokumentinhalt; `null` heißt: nicht (mehr) indizierbar → Dokument entfernen. */
    public function build(Model $model, SearchContext $context): ?SearchDocumentData;
}
