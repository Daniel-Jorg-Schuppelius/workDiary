<?php
/*
 * Created on   : Tue Oct 20 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : TemplateMerger.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Invoicing\Recognition;

use App\Models\Invoicing\{IncomingEInvoice, IncomingInvoiceTemplate};
use App\Models\Platform\User;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Zwei Belegvorlagen zusammenführen (Phase 155, E7): Zonen für Felder, die der
 * Zielvorlage fehlen, wandern mit; Eingänge werden umgehängt, Treffer addiert,
 * der Lieferant ergänzt; Fingerabdruck und Name der Zielvorlage bleiben. Die
 * Quelle wird gelöscht.
 */
final class TemplateMerger {
    /** @return array{moved: int, fields: list<string>} umgehängte Eingänge und übernommene Felder */
    public function merge(IncomingInvoiceTemplate $source, IncomingInvoiceTemplate $target, ?User $actor): array {
        if ($source->is($target) || (int) $source->organization_id !== (int) $target->organization_id) {
            throw new InvalidArgumentException('Templates must differ and belong to the same organization.');
        }

        return DB::transaction(function () use ($source, $target, $actor): array {
            $present = array_fill_keys($target->fieldKeys(), true);
            $added = [];
            $zones = $target->zoneList();
            foreach ($source->zoneList() as $zone) {
                $field = (string) ($zone['field'] ?? '');
                if ($field === '' || isset($present[$field])) {
                    continue;
                }
                $zones[] = $zone;
                $present[$field] = true;
                $added[] = $field;
            }
            $moved = IncomingEInvoice::query()->withoutGlobalScopes()
                ->where('organization_id', $target->organization_id)
                ->where('template_id', $source->id)
                ->update(['template_id' => $target->id]);
            $target->forceFill([
                'zones' => $zones,
                'hits' => (int) $target->hits + (int) $source->hits,
                'supplier_id' => $target->supplier_id ?? $source->supplier_id,
                'last_matched_at' => max($target->last_matched_at, $source->last_matched_at),
            ])->save();
            $target->audit('incoming_einvoice.template_merged', ['from' => $source->name, 'from_id' => $source->id, 'fields' => $added, 'moved' => $moved, 'actor_user_id' => $actor?->id]);
            $source->delete();

            return ['moved' => (int) $moved, 'fields' => $added];
        });
    }
}
