<?php
/*
 * Created on   : Thu Nov 12 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : PlaceOfSupplyResolver.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Invoicing;

use App\Enums\Article\ArticleType;
use App\Enums\Invoicing\PlaceOfSupplyKind;
use App\Models\Article\Article;
use App\Models\Invoicing\{Invoice, InvoiceItem};
use App\Models\Platform\Organization;
use App\Models\Project\Project;
use App\Models\Time\TimeEntry;
use App\Settings\SettingsRegistry;
use Illuminate\Database\Eloquent\Collection;

/**
 * Leistungsort der Leistungen eines Belegs (§ 3a UStG, MVP-1688). Die Art
 * steht dort, wo die Leistung definiert ist: Abrechnungsregel des Projekts →
 * deren Artikel → Standardleistung der Organisation → Einstellung
 * `invoicing.place_of_supply_kind` (ab Werk „allgemein“). Das Grundstück
 * liegt am Einsatzort des Auftrags, ohne Angabe im Inland.
 */
final class PlaceOfSupplyResolver {
    public function __construct(private readonly SettingsRegistry $settings) {}

    public function organizationDefault(Organization $organization): PlaceOfSupplyKind {
        return PlaceOfSupplyKind::tryFrom((string) $this->settings->effective('invoicing.place_of_supply_kind', $organization)->value)
            ?? PlaceOfSupplyKind::General;
    }

    /**
     * Leistungsorte der berechneten Positionen, ohne Doppel. Es zählen Zeiten
     * und Leistungsartikel; Positionen mit eigener Steuerkategorie, Material,
     * Lieferungen, Anfahrt, Auslagen und Abschlagsanrechnungen folgen dem
     * Beleg. Zählt keine, gilt die Leistung des Projekts.
     *
     * @return non-empty-list<PlaceOfSupply>
     */
    public function forInvoice(Invoice $invoice): array {
        $organization = $invoice->organization;
        if (! $organization instanceof Organization) {
            return [new PlaceOfSupply(PlaceOfSupplyKind::General)];
        }

        $items = new Collection($invoice->pricedItems()->filter(static fn (InvoiceItem $item): bool => trim((string) $item->tax_category) === ''
            && $item->settled_invoice_id === null && $item->material_usage_id === null && $item->stock_delivery_id === null
            && $item->tour_id === null && $item->expense_id === null)->all());
        $items->loadMissing(['timeEntries.project', 'timeEntries.diaryEntry', 'timeEntry.project', 'timeEntry.diaryEntry']);

        $services = app(ServiceDefaultResolver::class);
        $places = [];
        $articleIds = [];
        foreach ($items as $item) {
            $entries = $item->timeEntries->isNotEmpty() ? $item->timeEntries->all() : array_filter([$item->timeEntry]);
            foreach ($entries as $entry) {
                $place = $this->forTimeEntry($organization, $entry, $services);
                $places[$place->key()] = $place;
            }
            if ($entries === [] && $item->article_id !== null) {
                $articleIds[] = (int) $item->article_id;
            }
        }
        foreach ($this->forArticles($organization, $articleIds) as $place) {
            $places[$place->key()] = $place;
        }

        return $places !== [] ? array_values($places) : [$this->forProject($organization, $invoice->project)];
    }

    public function forTimeEntry(Organization $organization, TimeEntry $entry, ?ServiceDefaultResolver $services = null): PlaceOfSupply {
        $service = ($services ?? app(ServiceDefaultResolver::class))->resolve($organization, $entry->project, $entry->kind->value);

        return new PlaceOfSupply($service->placeOfSupplyKind ?? $this->organizationDefault($organization), $entry->diaryEntry?->address_country);
    }

    /** Leistung des Projekts ohne Tätigkeitsart (Regel für alle), sonst die Vorgabe der Organisation. */
    public function forProject(Organization $organization, ?Project $project): PlaceOfSupply {
        $service = app(ServiceDefaultResolver::class)->resolve($organization, $project, null);

        return new PlaceOfSupply($service->placeOfSupplyKind ?? $this->organizationDefault($organization));
    }

    /**
     * Leistungsorte von Leistungsartikeln des Artikelstamms; Waren zählen nicht.
     *
     * @param  list<int>  $articleIds
     * @return list<PlaceOfSupply>
     */
    public function forArticles(Organization $organization, array $articleIds): array {
        if ($articleIds === []) {
            return [];
        }
        $places = [];
        $articles = Article::query()->withoutGlobalScopes()
            ->where('organization_id', $organization->id)
            ->where('type', ArticleType::Service->value)
            ->whereKey(array_unique($articleIds))
            ->get(['id', 'place_of_supply_kind']);
        foreach ($articles as $article) {
            $place = new PlaceOfSupply($article->place_of_supply_kind ?? $this->organizationDefault($organization));
            $places[$place->key()] = $place;
        }

        return array_values($places);
    }
}
