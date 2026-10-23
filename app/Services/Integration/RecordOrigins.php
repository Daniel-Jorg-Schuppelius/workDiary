<?php
/*
 * Created on   : Fri Oct 23 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : RecordOrigins.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Integration;

use App\Models\Integration\ExternalReference;
use App\Plugins\Contracts\LinksExternalRecords;
use App\Plugins\PluginManager;
use CommonToolkit\Helper\Data\WebLinkHelper;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Herkunft übernommener Datensätze (Feature 181) aus der Fremdreferenz: Name
 * des Plugins und, wenn das Plugin ihn kennt, der Link ins Fremdsystem — nur
 * mit http(s)-Schema, weil der Wert aus dem Fremdsystem stammt.
 */
final class RecordOrigins {
    public function __construct(private readonly PluginManager $plugins) {}

    /** @return list<array{plugin: string, label: string, url: string|null, syncedAt: Carbon|null}> */
    public function for(Model $subject): array {
        $organizationId = $subject->getAttribute('organization_id');
        if ($organizationId === null) {
            return [];
        }

        return array_values(ExternalReference::query()->withoutGlobalScopes()
            ->where('organization_id', $organizationId)
            ->forReferenceable($subject)
            ->orderBy('id')
            ->get()
            ->map(function (ExternalReference $reference): array {
                $plugin = $this->plugins->find($reference->plugin_id);
                $url = $plugin instanceof LinksExternalRecords ? $plugin->externalRecordUrl($reference) : null;

                return [
                    'plugin' => $reference->plugin_id,
                    'label' => $plugin?->name() ?? $reference->plugin_id,
                    'url' => WebLinkHelper::isHttpUrl($url) ? $url : null,
                    'syncedAt' => $reference->synced_at,
                ];
            })
            ->all());
    }

    /**
     * Pluginname je Datensatz für Listen und Trefferzeilen — eine Abfrage
     * für alle IDs, die älteste Fremdreferenz gewinnt.
     *
     * @param  class-string<Model>  $modelClass
     * @param  array<int>  $ids
     * @return array<int, string>
     */
    public function labels(string $modelClass, array $ids, int $organizationId): array {
        if ($ids === []) {
            return [];
        }
        $labels = [];
        $references = ExternalReference::query()->withoutGlobalScopes()
            ->where('organization_id', $organizationId)
            ->where('referenceable_type', (new $modelClass)->getMorphClass())
            ->whereIn('referenceable_id', $ids)
            ->orderBy('id')
            ->get(['referenceable_id', 'plugin_id']);
        foreach ($references as $reference) {
            $labels[$reference->referenceable_id] ??= $this->plugins->find($reference->plugin_id)?->name() ?? $reference->plugin_id;
        }

        return $labels;
    }
}
