<?php
/*
 * Created on   : Sat May 23 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ProcedureApplicabilityResolver.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Services\Procedure;

use App\Models\Asset\Asset;
use App\Models\Diary\DiaryEntry;
use App\Models\Procedure\{ProcedureTemplate, ProcedureTemplateVersion};
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\{Carbon, Collection};

/**
 * Filtert veröffentlichte Prozedurvorlagen anhand des `applicability`-JSONs
 * ihrer aktuellen Version gegen einen Auftrag oder ein Asset
 * (../WorkDiary-Architecture/konzepte/prozedurvorlagen.md §4, MVP-1571).
 *
 * Kriterien (jedes gesetzte muss passen, innerhalb eines Kriteriums genügt ein Treffer):
 *  - `diary_entry_type`: Auftragstyp-Slugs.
 *  - `customer_ids`: Kunden.
 *  - `tags_any`: Tags am Auftrag.
 *  - `asset_categories`: Kategorie-Codes des Assets.
 *  - `product_ids`: Produkt des Assets.
 *  - `sla_contract_ids`: SLA-Vertrag des Assets.
 *
 * Ohne Kriterien gilt die Vorlage am Auftrag als universell. Am Asset wird
 * nur vorgeschlagen, was ein Asset-Kriterium nennt — sonst stünde jede
 * universelle Vorlage an jeder Asset-Akte.
 *
 * @phpstan-type ApplicabilityContext array{diary_entry_type?: ?string, customer_id?: ?int, tags?: list<string>, asset_category?: ?string, product_id?: ?int, sla_contract_id?: ?int}
 */
class ProcedureApplicabilityResolver {
    /** Kriterien, die der Designer pflegt; andere Schlüssel bleiben beim Speichern erhalten. */
    public const array RULE_KEYS = ['diary_entry_type', 'tags_any', 'customer_ids', 'asset_categories', 'product_ids', 'sla_contract_ids'];

    private const array ASSET_RULE_KEYS = ['asset_categories', 'product_ids', 'sla_contract_ids'];

    public function __construct(private readonly ProcedureTemplateService $templates) {}

    /**
     * @return Collection<int, ProcedureTemplate>
     */
    public function suggestFor(DiaryEntry $entry): Collection {
        return $this->matching((int) $entry->organization_id, $this->contextFor($entry), false);
    }

    /**
     * @return Collection<int, ProcedureTemplate>
     */
    public function suggestForAsset(Asset $asset): Collection {
        return $this->matching((int) $asset->organization_id, $this->contextFor($asset), true);
    }

    /**
     * @return ApplicabilityContext
     */
    public function contextFor(Model $subject): array {
        if ($subject instanceof DiaryEntry) {
            $asset = $subject->asset;

            return [
                'diary_entry_type' => $subject->entryType?->slug,
                'customer_id' => $subject->customer_id !== null ? (int) $subject->customer_id : null,
                'tags' => array_values(array_map('strval', $subject->relationLoaded('tags')
                    ? $subject->tags->pluck('name')->all()
                    : $subject->tags()->pluck('name')->all())),
            ] + $this->assetContext($asset);
        }

        if ($subject instanceof Asset) {
            return [
                'customer_id' => $subject->customer_id !== null ? (int) $subject->customer_id : null,
            ] + $this->assetContext($subject);
        }

        return [];
    }

    /**
     * Gilt die zum Stichtag gültige Version der Vorlage für diesen Kontext?
     * Am Asset (`$requireAssetRule`) nur mit Asset-Kriterium.
     *
     * @param  ApplicabilityContext  $context
     */
    public function templateAppliesAt(ProcedureTemplate $template, array $context, ?Carbon $at = null, bool $requireAssetRule = false): bool {
        $version = $this->templates->currentVersionFor($template, $at);

        return $version !== null && $this->matches($version, $context, $requireAssetRule);
    }

    /**
     * @param  ApplicabilityContext  $context
     * @return Collection<int, ProcedureTemplate>
     */
    private function matching(int $organizationId, array $context, bool $requireAssetRule): Collection {
        return ProcedureTemplate::query()
            ->withoutGlobalScopes()
            ->where('organization_id', $organizationId)
            ->where('active', true)
            ->get()
            ->filter(fn (ProcedureTemplate $template): bool => $this->templateAppliesAt($template, $context, null, $requireAssetRule))
            ->values();
    }

    /**
     * @return array{asset_category: ?string, product_id: ?int, sla_contract_id: ?int}
     */
    private function assetContext(?Asset $asset): array {
        return [
            'asset_category' => $asset?->category_code,
            'product_id' => $asset?->product_id !== null ? (int) $asset->product_id : null,
            'sla_contract_id' => $asset?->sla_contract_id !== null ? (int) $asset->sla_contract_id : null,
        ];
    }

    /**
     * @param  ApplicabilityContext  $context
     */
    private function matches(ProcedureTemplateVersion $version, array $context, bool $requireAssetRule): bool {
        $rules = $version->applicability ?? [];
        $list = static fn (string $key): array => is_array($rules[$key] ?? null) ? $rules[$key] : [];

        if ($requireAssetRule && array_filter(array_map($list, self::ASSET_RULE_KEYS)) === []) {
            return false;
        }

        $tags = $list('tags_any');
        if ($tags !== [] && array_intersect($tags, $context['tags'] ?? []) === []) {
            return false;
        }

        foreach ([
            'diary_entry_type' => $context['diary_entry_type'] ?? null,
            'customer_ids' => $context['customer_id'] ?? null,
            'asset_categories' => $context['asset_category'] ?? null,
            'product_ids' => $context['product_id'] ?? null,
            'sla_contract_ids' => $context['sla_contract_id'] ?? null,
        ] as $key => $value) {
            $allowed = $list($key);
            // Lose verglichen: IDs kommen aus JSON teils als Zeichenkette.
            if ($allowed !== [] && ($value === null || ! in_array((string) $value, array_map('strval', $allowed), true))) {
                return false;
            }
        }

        return true;
    }
}
