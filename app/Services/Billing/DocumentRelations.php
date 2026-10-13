<?php
/*
 * Created on   : Tue Oct 13 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : DocumentRelations.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Billing;

use App\Enums\Billing\DocumentRelationKind;
use App\Modules\ModuleRegistry;
use App\Services\Billing\Contracts\DocumentRelationSource;
use App\Services\Billing\Dto\{DocumentNode, DocumentRef};
use Illuminate\Database\Eloquent\Model;

/**
 * Belegverbund eines Belegs (MVP-1157): alle Belege, die über Bezüge lokal
 * oder im Buchhaltungsprogramm mit ihm zusammenhängen, per Breitensuche über
 * die eingetragenen {@see DocumentRelationSource}s.
 */
final class DocumentRelations {
    /** Obergrenze, damit ein Abrechnungsplan mit vielen Läufen die Seite nicht flutet. */
    public const LIMIT = 50;

    public function __construct(private readonly ModuleRegistry $modules) {}

    /**
     * Verbundene Belege ohne den Beleg selbst, nach Datum. `via` ist der Beleg,
     * über den der Bezug besteht; null = direkt mit dem betrachteten.
     *
     * @return list<array{node: DocumentNode, kind: DocumentRelationKind, via: ?DocumentNode}>
     */
    public function of(Model $document): array {
        $organizationId = (int) $document->getAttribute('organization_id');
        $sources = [];
        foreach ($this->modules->extensions(DocumentRelationSource::class) as $class) {
            $source = app($class);
            if ($source instanceof DocumentRelationSource) {
                $sources[] = $source;
            }
        }

        $start = DocumentRef::forModel($document);
        $seen = [$start->key() => true];
        // Warteschlange: Beleg und der Knoten, über den er gefunden wurde (null = Start).
        $refs = [$start];
        $vias = [null];
        $found = [];
        $keys = [];
        for ($i = 0; $i < count($refs) && count($found) < self::LIMIT; $i++) {
            foreach ($sources as $source) {
                foreach ($source->relationsOf($refs[$i], $organizationId) as $relation) {
                    $key = $relation->node->ref->key();
                    if (isset($seen[$key]) || count($found) >= self::LIMIT) {
                        continue;
                    }
                    $seen[$key] = true;
                    $found[] = ['node' => $relation->node, 'kind' => $relation->kind, 'via' => $vias[$i]];
                    // Laufnummer zuletzt: Gleichstand fiele sonst auf den Vergleich der Knoten durch.
                    $keys[] = [$relation->node->date?->getTimestamp() ?? PHP_INT_MAX, (string) $relation->node->number, count($keys)];
                    $refs[] = $relation->node->ref;
                    $vias[] = $relation->node;
                }
            }
        }

        // Nach Datum (ohne Datum zuletzt), dann Nummer.
        array_multisort($keys, $found);

        return $found;
    }
}
