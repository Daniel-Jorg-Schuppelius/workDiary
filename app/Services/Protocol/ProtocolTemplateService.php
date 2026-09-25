<?php
/*
 * Created on   : Fri Sep 25 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ProtocolTemplateService.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Services\Protocol;

use App\Models\Asset\Asset;
use App\Models\Customer\Customer;
use App\Models\Diary\DiaryEntry;
use App\Models\Platform\User;
use App\Models\Project\Project;
use App\Models\Protocol\{Protocol, ProtocolItem, ProtocolTemplate};
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

/**
 * Protokollvorlagen (MVP-901): Auswahl passend zum Bezug und Übernahme der
 * Punkte eines vorhandenen Protokolls. Eine Vorlage speichert nur die
 * Konfiguration, nie erfasste Werte.
 */
final class ProtocolTemplateService {
    /** Konfigurationsschlüssel im value_json, die eine Vorlage übernimmt. */
    public const CONFIG_KEYS = ['options', 'unit', 'min', 'max', 'min_length', 'max_length', 'min_count', 'max_count', 'min_per_phase', 'result_map', 'tolerance_min', 'tolerance_max'];

    /** @return Collection<int, ProtocolTemplate> */
    public function applicableFor(Model $subject): Collection {
        [$entryTypeId, $customerId] = match (true) {
            $subject instanceof DiaryEntry => [$subject->entry_type_id, $subject->customer_id],
            $subject instanceof Project, $subject instanceof Asset => [null, $subject->customer_id],
            $subject instanceof Customer => [null, $subject->id],
            default => [null, null],
        };

        return ProtocolTemplate::query()->usable()
            ->where('organization_id', (int) $subject->getAttribute('organization_id'))
            ->orderBy('name')->get()
            ->filter(fn (ProtocolTemplate $t): bool => $t->matches(
                $entryTypeId !== null ? (int) $entryTypeId : null,
                $customerId !== null ? (int) $customerId : null,
            ))
            ->values();
    }

    /**
     * Neue Vorlage aus einem Protokoll oder neue Version einer bestehenden.
     *
     * @param array{name: string, entry_type_id?: ?int, customer_id?: ?int, valid_from?: ?string, valid_until?: ?string} $data
     */
    public function fromProtocol(Protocol $protocol, array $data, User $actor, ?ProtocolTemplate $existing = null): ProtocolTemplate {
        $items = $this->snapshot($protocol);
        if ($existing instanceof ProtocolTemplate) {
            $existing->forceFill([
                'items' => $items,
                'kind' => $protocol->type->value,
                'version' => $existing->version + 1,
                'updated_by' => $actor->id,
            ])->save();

            return $existing;
        }

        return ProtocolTemplate::query()->create([
            'organization_id' => $protocol->organization_id,
            'name' => $data['name'],
            'kind' => $protocol->type->value,
            'items' => $items,
            'entry_type_id' => $data['entry_type_id'] ?? null,
            'customer_id' => $data['customer_id'] ?? null,
            'valid_from' => $data['valid_from'] ?? null,
            'valid_until' => $data['valid_until'] ?? null,
            'created_by' => $actor->id,
            'updated_by' => $actor->id,
        ]);
    }

    /** @return list<array<string, mixed>> */
    private function snapshot(Protocol $protocol): array {
        $items = $protocol->items()->whereNull('parent_item_id')->with('children')->orderBy('sort_order')->get();

        return array_values($items->map(fn (ProtocolItem $item): array => $this->spec($item, true))->all());
    }

    /** @return array<string, mixed> */
    private function spec(ProtocolItem $item, bool $withChildren): array {
        $value = is_array($item->value_json) ? $item->value_json : [];
        $spec = array_filter([
            'label' => $item->label,
            'item_type' => $item->item_type->value,
            'description' => $item->description,
            'required' => $item->required ?: null,
            'config' => array_intersect_key($value, array_flip(self::CONFIG_KEYS)) ?: null,
        ], static fn (mixed $v): bool => $v !== null);
        if ($withChildren && $item->children->isNotEmpty()) {
            $spec['children'] = $item->children->sortBy('sort_order')->map(fn (ProtocolItem $child): array => $this->spec($child, false))->values()->all();
        }

        return $spec;
    }
}
