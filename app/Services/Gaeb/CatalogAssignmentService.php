<?php
/*
 * Created on   : Tue Aug 18 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : CatalogAssignmentService.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Gaeb;

use App\Models\{BillOfQuantity, BoqCatalog, BoqCatalogAssignment, BoqItem};
use App\Models\Catalog\{CatalogEntry, CatalogRegistry};
use Illuminate\Database\Eloquent\Model;
use RuntimeException;

/**
 * Katalogzuordnungen setzen und lösen (Feature 109, MVP-639).
 *
 * **Die Herkunft bleibt am Datensatz.** Eine Zuordnung aus der Datei der
 * Vergabestelle (`import`) ist etwas anderes als eine von Hand gesetzte
 * (`manual`) oder eine vorgeschlagene (`rule`): Beim Reimport darf die
 * importierte überschrieben werden, die von Hand gesetzte nicht — sonst
 * verlöre man beim nächsten Nachtrag die eigene Arbeit.
 *
 * **Ein Code, den der Stamm nicht kennt, wird abgewiesen.** Die Auswertung
 * summiert nach Nummern; eine falsche Nummer fiele niemandem auf, sie stünde
 * nur in einer Zeile, die es nicht geben dürfte.
 */
final class CatalogAssignmentService {
    public const SOURCE_IMPORT = 'import';
    public const SOURCE_MANUAL = 'manual';
    public const SOURCE_RULE = 'rule';

    /**
     * Setzt die Zuordnung eines Elements für einen Katalog. Ein leerer Code
     * entfernt sie.
     *
     * @param BoqItem|\App\Models\BoqSection|\App\Models\BoqItemQuantitySplit $target
     */
    public function assign(Model $target, BoqCatalog $catalog, ?string $code, string $source = self::SOURCE_MANUAL): ?BoqCatalogAssignment {
        $code = trim((string) $code);
        $billId = (int) $catalog->bill_of_quantity_id;

        $existing = BoqCatalogAssignment::query()
            ->where('bill_of_quantity_id', $billId)
            ->where('catalog_key', $catalog->catalog_key)
            ->where('assignable_type', $target->getMorphClass())
            ->where('assignable_id', $target->getKey())
            ->first();

        if ($code === '') {
            $existing?->delete();

            return null;
        }

        $this->guardCode($catalog, $code);

        if ($existing !== null) {
            $existing->update(['code' => $code, 'source' => $source]);

            return $existing;
        }

        return BoqCatalogAssignment::query()->create([
            'organization_id' => $catalog->organization_id,
            'bill_of_quantity_id' => $billId,
            'assignable_type' => $target->getMorphClass(),
            'assignable_id' => $target->getKey(),
            'catalog_key' => $catalog->catalog_key,
            'code' => $code,
            'source' => $source,
        ]);
    }

    /**
     * Setzt denselben Code auf mehrere Positionen.
     *
     * Von Hand gesetzte Zuordnungen werden dabei **überschrieben** — wer eine
     * Massenzuordnung auslöst, meint genau das. Der Schutz vor stillem
     * Überschreiben gilt dem Reimport, nicht der bewussten Handlung.
     *
     * @param  iterable<BoqItem> $items
     * @return int Zahl der geänderten Positionen
     */
    public function assignMany(iterable $items, BoqCatalog $catalog, ?string $code, string $source = self::SOURCE_MANUAL): int {
        $count = 0;
        foreach ($items as $item) {
            $this->assign($item, $catalog, $code, $source);
            $count++;
        }

        return $count;
    }

    /**
     * Auswahlliste eines Katalogs — die Einträge des passenden Stamms.
     *
     * Ohne Stamm bleibt die Liste leer: Dann trägt die Datei einen Katalog, den
     * wir nicht kennen, und eine erfundene Auswahl wäre schlimmer als keine.
     *
     * @return array<string, string> Code → „310 Baugrube, Erdbau"
     */
    public function options(BoqCatalog $catalog): array {
        $registry = $this->registryFor($catalog);
        if ($registry === null) {
            return [];
        }

        $options = [];
        foreach ($registry->entries()->orderBy('code')->get() as $entry) {
            $options[$entry->code] = $entry->display();
        }

        return $options;
    }

    public function registryFor(BoqCatalog $catalog): ?CatalogRegistry {
        $type = trim((string) $catalog->type);
        if ($type === '') {
            return null;
        }

        return CatalogRegistry::query()->where('gaeb_type', $type)->where('active', true)->first();
    }

    /** Kostengruppenkatalog eines Verzeichnisses — ein LV führt selten mehrere. */
    public function costGroupCatalog(BillOfQuantity $boq): ?BoqCatalog {
        return BoqCatalog::query()
            ->where('bill_of_quantity_id', $boq->id)
            ->get()
            ->first(static fn (BoqCatalog $catalog): bool => $catalog->isCostGroup());
    }

    /**
     * Kennt der Stamm diesen Code? Ohne Stamm wird durchgelassen — dann gibt es
     * nichts, wogegen zu prüfen wäre.
     */
    private function guardCode(BoqCatalog $catalog, string $code): void {
        $registry = $this->registryFor($catalog);
        if ($registry === null) {
            return;
        }

        $known = CatalogEntry::query()
            ->where('catalog_registry_id', $registry->id)
            ->where('code', $code)
            ->exists();

        if (! $known) {
            throw new RuntimeException((string) __('Der Schlüssel :code steht nicht im Katalog :catalog.', [
                'code' => $code,
                'catalog' => $registry->name,
            ]));
        }
    }
}
