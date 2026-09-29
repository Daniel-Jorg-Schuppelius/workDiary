<?php
/*
 * Created on   : Tue Sep 29 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ArticlesMerged.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Events\Article;

use Illuminate\Foundation\Events\Dispatchable;

/**
 * Zwei Artikel des Artikelstamms wurden zusammengeführt (MVP-1025). Synchron in
 * der Merge-Transaktion: Module mit Katalogschlüsseln (`art:<id>`) hängen ihre
 * Bezüge um — Fremdschlüssel gibt es für diese Schlüssel nicht.
 */
final class ArticlesMerged {
    use Dispatchable;

    public function __construct(
        public readonly int $organizationId,
        public readonly int $sourceId,
        public readonly int $targetId,
    ) {}
}
