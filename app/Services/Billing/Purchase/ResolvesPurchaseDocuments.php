<?php
/*
 * Created on   : Wed Oct 21 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ResolvesPurchaseDocuments.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Billing\Purchase;

use App\Models\Platform\Organization;
use App\Support\Sqid;
use Illuminate\Database\Eloquent\{Builder, Model};
use Illuminate\Support\Collection;

/**
 * Nachschlagen einzelner Einkaufsbelege, gleich für jede Quelle
 * (Konsolidierungs-Audit 2026-10-21, ko-20). Die Quelle nennt ihr Modell in
 * `DOCUMENT_MODEL` und liefert Abfrage, zuteilbare Kandidaten und Abbildung.
 */
trait ResolvesPurchaseDocuments {
    /** @return Builder<Model> */
    abstract private function query(Organization $organization): Builder;

    /** @return Builder<Model> */
    abstract private function candidates(Organization $organization): Builder;

    /**
     * @param  Collection<int, Model>  $rows
     * @return Collection<int, PurchaseDocument>
     */
    abstract private function map(Collection $rows): Collection;

    public function byKey(Organization $organization, string $key): ?PurchaseDocument {
        $id = Sqid::decode(self::DOCUMENT_MODEL, $key);
        if ($id === null) {
            return null;
        }
        $model = $this->candidates($organization)->whereKey($id)->first();

        return $model === null ? null : $this->map(collect([$model]))->first();
    }

    public function byMorph(Organization $organization, int $id): ?PurchaseDocument {
        return $this->byMorphIds($organization, [$id])->get($id);
    }

    public function byMorphIds(Organization $organization, array $ids): Collection {
        if ($ids === []) {
            return collect();
        }

        return $this->map($this->query($organization)->whereIn('id', $ids)->get())
            ->keyBy(static fn (PurchaseDocument $document): int => $document->morphId);
    }
}
