<?php
/*
 * Created on   : Tue Sep 29 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : PartyDocumentList.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Billing\Dto;

use App\Support\Ui\UiAction;

final readonly class PartyDocumentList {
    /**
     * @param  string  $source  Anzeigename der Quelle in der Spalte „Quelle“
     * @param  bool  $linked  Partei im Fremdsystem verknüpft
     * @param  list<PartyDocument>  $documents
     * @param  ?UiAction  $refresh  Abgleich aus dem Kartenkopf, nur verknüpft
     * @param  ?string  $unlinkedHint  Leerzustand, solange nicht verknüpft
     */
    public function __construct(
        public string $source,
        public bool $linked,
        public array $documents = [],
        public ?UiAction $refresh = null,
        public ?string $unlinkedHint = null,
    ) {}
}
