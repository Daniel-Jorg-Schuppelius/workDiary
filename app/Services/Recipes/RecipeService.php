<?php
/*
 * Created on   : Thu Jul 30 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : RecipeService.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Recipes;

use App\Enums\Classification\ClassificationDomain;
use App\Models\{Article, ArticleUnit, Organization, ProcedureTemplate, ProcedureTemplateVersion};
use App\Models\Recipes\{RecipeMenu, RecipeProfile};
use App\Services\Manufacturing\{BomResolver, MaterialDemandCalculator};
use CommonToolkit\ValueObjects\Money;
use Illuminate\Validation\ValidationException;

/**
 * Partyservice-Rezepturen (MVP-455) auf dem branchenneutralen Rezeptkern aus
 * MVP-061: Skalierung nach Portionen/Gästezahl über den vorhandenen
 * {@see MaterialDemandCalculator} (fest/je Einheit/Verhältnis, Verschnitt,
 * Rundung), Plankosten aus den Artikelkosten, Allergenvererbung von den
 * Zutaten auf Gericht und Menü sowie die Menüaggregation ohne
 * Positionsduplikate. Partyservice-Spezifika greifen NUR, wenn das
 * Branchenprofil `partyservice` installiert ist — technische Rezepturen
 * anderer Gewerke bleiben unberührt.
 */
class RecipeService {
    public const PROFILE_CODE = 'partyservice';

    /** LMIV-Sonderfall: „keine" markiert eine geklärt allergenfreie Zutat. */
    public const ALLERGEN_NONE = 'keine';

    public function __construct(
        private readonly BomResolver $bom,
        private readonly MaterialDemandCalculator $calculator,
    ) {}

    /** Branchenprofil-Kontext (Muster {@see \App\Services\Ai\AiRoutingResolver}). */
    public function isPartyserviceActive(Organization $organization): bool {
        $settings = is_array($organization->settings) ? $organization->settings : [];
        if (($settings['branch_profile_code'] ?? null) === self::PROFILE_CODE) {
            return true;
        }

        return data_get($settings, 'branch_profile_versions.' . self::PROFILE_CODE) !== null;
    }

    /** Aktuell veröffentlichte Rezeptversion eines Gerichts (Stichtag heute). */
    public function publishedVersionFor(ProcedureTemplate $template): ?ProcedureTemplateVersion {
        $today = now()->toDateString();

        return $template->versions()
            ->whereNotNull('published_at')
            ->where(function ($q) use ($today): void {
                $q->whereNull('valid_from')->orWhere('valid_from', '<=', $today);
            })
            ->where(function ($q) use ($today): void {
                $q->whereNull('valid_to')->orWhere('valid_to', '>=', $today);
            })
            ->orderByDesc('version')
            ->first();
    }

    /**
     * Materialbedarf für eine Ziel-Portionszahl (dezimalgenau, einheitensicher).
     *
     * @param  numeric-string  $portions
     * @return list<array{requirement: \App\Models\ProcedureMaterialRequirement, demand: numeric-string}>
     */
    public function demandForPortions(ProcedureTemplateVersion $version, string $portions): array {
        return $this->calculator->calculate($this->bom->resolve($version, null), $portions);
    }

    /**
     * Plankosten aus den Artikel-Einkaufskosten; Einheiten werden über
     * `ArticleUnit::factor_to_base` auf die Basiseinheit umgerechnet. Zeilen
     * ohne Preis oder ohne Umrechnung erscheinen unter `incomplete` — keine
     * stillen Lücken.
     *
     * @param  numeric-string  $portions
     * @return array{total: Money|null, per_portion: Money|null, lines: list<array{label: string, demand: numeric-string, unit: string, cost: Money|null}>, incomplete: list<string>}
     */
    public function planCosts(ProcedureTemplateVersion $version, string $portions): array {
        $lines = [];
        $incomplete = [];
        $total = null;

        foreach ($this->demandForPortions($version, $portions) as $row) {
            $req = $row['requirement'];
            if ($req->is_tool) {
                continue;
            }
            /** @var Article|null $article */
            $article = $req->article;
            $label = $article !== null ? $article->number . ' — ' . $article->name : $req->position_code;

            $cost = null;
            if ($article?->default_purchase_price instanceof Money) {
                $factor = $this->factorToBase($article, (string) $req->unit);
                if ($factor === null) {
                    $incomplete[] = (string) __('recipes.costs.unit_unmapped', ['article' => $label, 'unit' => $req->unit]);
                } else {
                    $cost = $article->default_purchase_price->times(bcmul($row['demand'], $factor, 6));
                    $total = $total instanceof Money && $total->isSameCurrency($cost)
                        ? $total->plus($cost)
                        : ($total === null ? $cost : $total);
                }
            } else {
                $incomplete[] = (string) __('recipes.costs.price_missing', ['article' => $label]);
            }

            $lines[] = ['label' => $label, 'demand' => $row['demand'], 'unit' => (string) $req->unit, 'cost' => $cost];
        }

        $perPortion = null;
        if ($total instanceof Money && bccomp($portions, '0', 4) > 0) {
            $perPortion = $total->dividedBy($portions);
        }

        return ['total' => $total, 'per_portion' => $perPortion, 'lines' => $lines, 'incomplete' => $incomplete];
    }

    /**
     * Allergenvererbung: abgeleitet aus den Allergen-Klassifikationen der
     * Zutaten-Artikel; `keine` gilt als geklärt allergenfrei. Zutaten ohne
     * jede Zuordnung bleiben als `unresolved` sichtbar. Manuelle
     * Abweichungen (Profil-Overrides) fließen in `effective` ein.
     *
     * @return array{derived: array<string, list<string>>, unresolved: list<string>, unresolved_ids: list<int>, effective: list<string>, overrides: RecipeProfile|null}
     */
    public function allergens(ProcedureTemplateVersion $version): array {
        $derived = [];
        $unresolved = [];
        $unresolvedIds = [];

        $requirements = $this->bom->resolve($version, null)
            ->filter(fn($req): bool => ! $req->is_tool);
        $articles = Article::query()
            ->whereIn('id', $requirements->pluck('article_id')->filter()->unique()->values())
            ->with(['classifications' => fn($q) => $q->where('domain', ClassificationDomain::Allergen->value)])
            ->get()
            ->keyBy('id');

        foreach ($requirements as $req) {
            /** @var Article|null $article */
            $article = $articles->get($req->article_id);
            if ($article === null) {
                continue;
            }
            $codes = $article->classifications->pluck('code')->all();
            if ($codes === []) {
                $unresolved[] = $article->number . ' — ' . $article->name;
                $unresolvedIds[] = (int) $article->id;

                continue;
            }
            foreach ($codes as $code) {
                if ($code === self::ALLERGEN_NONE) {
                    continue;
                }
                $derived[(string) $code] ??= [];
                $derived[(string) $code][] = (string) $article->name;
            }
        }

        $profile = $this->profileFor($version);
        $effective = array_keys($derived);
        if ($profile instanceof RecipeProfile) {
            $effective = array_values(array_unique(array_merge($effective, $profile->addedAllergens())));
            $effective = array_values(array_diff($effective, $profile->removedAllergens()));
        }
        sort($effective);
        ksort($derived);

        return [
            'derived' => $derived,
            'unresolved' => array_values(array_unique($unresolved)),
            'unresolved_ids' => array_values(array_unique($unresolvedIds)),
            'effective' => $effective,
            'overrides' => $profile,
        ];
    }

    /**
     * Menü-Allergene: Vereinigung der effektiven Allergene aller Gerichte
     * (jeweils aktuell veröffentlichter Stand).
     *
     * @return array{effective: list<string>, unresolved: list<string>}
     */
    public function allergensForMenu(RecipeMenu $menu): array {
        $effective = [];
        $unresolved = [];
        foreach ($menu->items as $item) {
            $template = $item->template;
            $version = $template !== null ? $this->publishedVersionFor($template) : null;
            if ($version === null) {
                continue;
            }
            $set = $this->allergens($version);
            $effective = array_merge($effective, $set['effective']);
            $unresolved = array_merge($unresolved, $set['unresolved']);
        }
        $effective = array_values(array_unique($effective));
        sort($effective);

        return ['effective' => $effective, 'unresolved' => array_values(array_unique($unresolved))];
    }

    /**
     * Freigabe-Guard (nur Partyservice-Rezepte): ungeklärte Allergene
     * blockieren die Veröffentlichung, sofern keine begründete manuelle
     * Abweichung vorliegt. Technische Rezepturen ohne Profil sind nie
     * betroffen.
     *
     * @throws ValidationException
     */
    public function assertPublishable(ProcedureTemplateVersion $version): void {
        $profile = $this->profileFor($version);
        if (! $profile instanceof RecipeProfile) {
            return;
        }

        $organization = $version->template?->organization;
        if ($organization === null || ! $this->isPartyserviceActive($organization)) {
            return;
        }

        $set = $this->allergens($version);
        if ($set['unresolved'] !== [] && $profile->overrideReason() === null) {
            throw ValidationException::withMessages([
                'allergens' => (string) __('recipes.error.allergens_unresolved', [
                    'articles' => implode(', ', $set['unresolved']),
                ]),
            ]);
        }
    }

    /**
     * Menüaggregation: Gästezahl × Portionen je Gast je Gericht, Bedarfe der
     * veröffentlichten Rezeptstände nach Artikel+Einheit zusammengeführt —
     * Rezeptpositionen werden nicht dupliziert, nur summiert.
     *
     * @return array{dishes: list<array{item: \App\Models\Recipes\RecipeMenuItem, version: ProcedureTemplateVersion|null, portions: numeric-string}>, materials: list<array{article: Article|null, label: string, unit: string, demand: numeric-string}>, missing_published: list<string>}
     */
    public function aggregateMenu(RecipeMenu $menu, int $guestCount): array {
        $dishes = [];
        $materials = [];
        $missing = [];

        foreach ($menu->items as $item) {
            $template = $item->template;
            $version = $template !== null ? $this->publishedVersionFor($template) : null;
            $portions = bcmul((string) $guestCount, (string) $item->portions_per_guest, 2);
            $dishes[] = ['item' => $item, 'version' => $version, 'portions' => $portions];

            if ($version === null) {
                $missing[] = (string) ($template->name ?? '?');

                continue;
            }

            foreach ($this->demandForPortions($version, $portions) as $row) {
                $req = $row['requirement'];
                if ($req->is_tool) {
                    continue;
                }
                $key = $req->article_id . '|' . $req->unit;
                if (! isset($materials[$key])) {
                    /** @var Article|null $article */
                    $article = $req->article;
                    $materials[$key] = [
                        'article' => $article,
                        'label' => $article !== null ? $article->number . ' — ' . $article->name : $req->position_code,
                        'unit' => (string) $req->unit,
                        'demand' => '0',
                    ];
                }
                $materials[$key]['demand'] = bcadd($materials[$key]['demand'], $row['demand'], 4);
            }
        }

        ksort($materials);

        return [
            'dishes' => $dishes,
            'materials' => array_values($materials),
            'missing_published' => $missing,
        ];
    }

    private function profileFor(ProcedureTemplateVersion $version): ?RecipeProfile {
        return RecipeProfile::query()
            ->withoutGlobalScopes()
            ->where('procedure_template_version_id', $version->id)
            ->first();
    }

    /**
     * Umrechnungsfaktor Bedarfseinheit → Artikel-Basiseinheit; `null` wenn
     * keine Umrechnung bekannt ist.
     *
     * @return numeric-string|null
     */
    private function factorToBase(Article $article, string $unit): ?string {
        if (trim($unit) === '' || strcasecmp($unit, (string) $article->base_unit) === 0) {
            return '1';
        }

        $articleUnit = ArticleUnit::query()
            ->where('article_id', $article->id)
            ->where('code', $unit)
            ->where('active', true)
            ->first();

        $factor = $articleUnit !== null ? (string) $articleUnit->factor_to_base : null;

        return $factor !== null && is_numeric($factor) ? $factor : null;
    }
}
