<?php
/*
 * Created on   : Thu Aug 20 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : AssetComponentService.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Asset;

use App\Models\{Asset, AssetComponent, MaterialUsage, User};
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Anlagen-Stückliste (Feature 118, MVP-607).
 *
 * Die Liste beantwortet beim Wartungseinsatz die Frage, die sonst erst vor
 * Ort auffällt: Welcher Filter, welche Dichtung, welche Baugröße? Der zweite
 * Anfahrtsweg ist der eigentliche Schaden, den sie verhindert.
 */
class AssetComponentService {
    /**
     * Verbaute Teile eines Assets.
     *
     * @return Collection<int, AssetComponent>
     */
    public function installed(Asset $asset): Collection {
        return AssetComponent::query()
            ->with('article:id,name,number,base_unit')
            ->where('asset_id', $asset->id)
            ->where('status', AssetComponent::STATUS_INSTALLED)
            ->orderBy('position')
            ->get();
    }

    /**
     * Historie inklusive ausgebauter Teile — „was war vorher drin" ist bei
     * wiederkehrenden Defekten die entscheidende Frage.
     *
     * @return Collection<int, AssetComponent>
     */
    public function history(Asset $asset): Collection {
        return AssetComponent::query()
            ->with('article:id,name,number')
            ->where('asset_id', $asset->id)
            ->orderByDesc('installed_on')
            ->orderByDesc('id')
            ->get();
    }

    /**
     * Fällige Verschleißteile als **Vorschlag** für den Wartungseinsatz.
     * Vorschlag heißt Vorschlag: Der Monteur entscheidet, was wirklich
     * getauscht wird.
     *
     * @return Collection<int, AssetComponent>
     */
    public function dueComponents(Asset $asset, ?CarbonImmutable $reference = null): Collection {
        $today = $reference ?? CarbonImmutable::today();

        return $this->installed($asset)->filter(
            fn (AssetComponent $component): bool => $component->isDue($today),
        )->values();
    }

    /**
     * Teil ersetzen: Das alte bleibt mit Ausbaudatum stehen und zeigt auf das
     * neue. Ein Überschreiben würde die Historie vernichten, die den Zweck der
     * Liste ausmacht.
     *
     * @param array<string, mixed> $attributes
     */
    public function replace(AssetComponent $old, array $attributes, ?User $actor = null): AssetComponent {
        if (! $old->isInstalled()) {
            throw new RuntimeException((string) __('asset.components.not_installed'));
        }

        return DB::transaction(function () use ($old, $attributes, $actor): AssetComponent {
            $new = AssetComponent::query()->create($attributes + [
                'organization_id' => $old->organization_id,
                'asset_id' => $old->asset_id,
                'installed_on' => CarbonImmutable::today()->toDateString(),
                'status' => AssetComponent::STATUS_INSTALLED,
                'created_by' => $actor?->id,
            ]);

            $old->forceFill([
                'status' => AssetComponent::STATUS_REPLACED,
                'removed_on' => CarbonImmutable::today()->toDateString(),
                'replaced_by_id' => $new->id,
            ])->save();

            $old->audit('assetComponent.replaced', ['replaced_by' => $new->id, 'by' => $actor?->id]);

            return $new;
        });
    }

    /**
     * Verbrauchtes Material als Komponente übernehmen — damit die Liste ohne
     * doppelte Pflege wächst.
     */
    public function adoptFromUsage(MaterialUsage $usage, Asset $asset, ?User $actor = null): AssetComponent {
        if ((int) $asset->organization_id !== (int) $usage->organization_id) {
            throw new RuntimeException((string) __('asset.components.foreign_organization'));
        }

        $component = AssetComponent::query()->create([
            'organization_id' => $asset->organization_id,
            'asset_id' => $asset->id,
            'label' => (string) ($usage->description ?? '—'),
            'quantity' => (string) $usage->quantity,
            'unit' => $usage->unit,
            'installed_on' => CarbonImmutable::today()->toDateString(),
            'created_by' => $actor?->id,
        ]);
        $component->audit('assetComponent.adopted', ['material_usage_id' => $usage->id]);

        return $component;
    }
}
