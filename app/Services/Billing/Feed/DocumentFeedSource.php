<?php
/*
 * Created on   : Mon Aug 24 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : DocumentFeedSource.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Billing\Feed;

use App\Services\Billing\DocumentFeedFilters;
use Illuminate\Database\Query\Builder;

/**
 * Eine Belegquelle des Belegflusses (Feature 105; Vollscan 2026-08, B9).
 *
 * Jede Quelle projiziert ihre Tabelle auf die gemeinsame Zeilenform; die
 * {@see \App\Services\Billing\DocumentFeedQuery} vereinigt alle Quellen per
 * `UNION ALL`. Die Spaltenliste ist der Vertrag — Namen und Reihenfolge
 * müssen exakt {@see FeedProjection::COLUMNS} entsprechen:
 *
 *   source_type, source_id, link_id, origin, direction, kind, sign, number,
 *   doc_date, due_on, state, is_archived, contact_type, contact_id,
 *   contact_name, dunning_level, amount_gross, open_amount, currency
 *
 * Kern-Quellen werden in der {@see DocumentFeedSourceRegistry}-Bindung
 * (AppServiceProvider) registriert; Plugin-Quellen registriert der jeweilige
 * Plugin-ServiceProvider selbst — der Kern kennt keine Plugin-Tabellennamen.
 */
interface DocumentFeedSource {
    /** Stabiler Registry-Schlüssel; erneute Registrierung ersetzt die Quelle. */
    public function key(): string;

    /**
     * Sub-Select dieser Quelle oder null, wenn die Filter sie ausschließen.
     * Die Sichtbarkeitsprüfung (`allows()`, `wantsOrigin()`, `wantsFixed()`)
     * liegt bewusst in der Quelle: nur sie kennt ihre feste Richtung/Art.
     */
    public function builder(DocumentFeedFilters $filters): ?Builder;
}
