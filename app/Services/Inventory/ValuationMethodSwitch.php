<?php
/*
 * Created on   : Tue Nov 10 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ValuationMethodSwitch.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Inventory;

use App\Enums\Inventory\ValuationMethod;
use App\Models\Article\{Article, ArticleVariant};
use App\Models\Inventory\{StockValuation, StockValuationLayer};
use App\Models\Platform\Organization;
use App\Services\Article\Contracts\ArticleValuation;
use App\Settings\SettingsRegistry;
use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Wechsel des Bewertungsverfahrens je Organisation oder Artikel (Feature 048,
 * E3, MVP-1579). Durchschnitt und Schichten führen getrennte Bücher
 * (`stock_valuations` gegen `stock_valuation_layers`): der Wechsel zu FIFO/FEFO
 * legt für vorhandenen Bestand Anfangsschichten an; der Wechsel zurück zum
 * Durchschnitt ist gesperrt, solange Schichten Bestand tragen, weil der
 * Durchschnitt währenddessen nicht fortgeschrieben wurde.
 */
class ValuationMethodSwitch implements ArticleValuation {
    public const string SETTING = 'inventory.valuation_method';

    public function __construct(
        private readonly ValuationMethodResolver $resolver,
        private readonly ValuationBackfillService $backfill,
    ) {}

    /**
     * Stellt das Verfahren der Organisation um (null = Vorgabe).
     *
     * @return int angelegte Anfangsschichten
     */
    public function switchOrganization(Organization $organization, ?ValuationMethod $to): int {
        $from = $this->resolver->methodFor($organization);
        $target = $to ?? ValuationMethod::MovingAverage;
        // Artikel mit eigenem Verfahren folgen der Organisation nicht.
        $variants = $this->variantIds($organization, fn (Builder $q) => $q->whereNull('valuation_method'));
        $this->assertSwitchable($from, $target, $variants, 'valuation_method');

        return DB::transaction(function () use ($organization, $to, $from, $target, $variants): int {
            app(SettingsRegistry::class)->setServiceManaged(self::SETTING, $to?->value, $organization);
            // Altformat ohne Gruppe (vor MVP-1579 nur von Hand gesetzt) mit abräumen.
            $settings = (array) ($organization->settings ?? []);
            if (array_key_exists('valuation_method', $settings)) {
                unset($settings['valuation_method']);
                $organization->settings = $settings;
                $organization->save();
            }

            return $this->carryOver($from, $target, $organization, $variants);
        });
    }

    public function organizationMethod(Organization $organization): ValuationMethod {
        return $this->resolver->methodFor($organization);
    }

    public function saveArticle(Article $article, array $attributes, Organization $organization, Closure $save): void {
        $before = $this->effective($article->valuation_method, $organization);
        $variants = $this->variantIds($organization, fn (Builder $q) => $q->whereKey($article->id));
        if (array_key_exists('valuation_method', $attributes)) {
            $this->assertSwitchable($before, $this->effective($attributes['valuation_method'], $organization), $variants, 'valuation_method');
        }

        DB::transaction(function () use ($article, $organization, $save, $before, $variants): void {
            $save();
            $this->carryOver($before, $this->effective($article->valuation_method, $organization), $organization, $variants);
        });
    }

    /** Wirksames Verfahren eines Artikels (eigenes Verfahren vor der Organisation). */
    private function effective(?string $articleMethod, Organization $organization): ValuationMethod {
        return ValuationMethod::tryFrom((string) $articleMethod) ?? $this->resolver->methodFor($organization);
    }

    /**
     * Überträgt den Bestand ins Buch des neuen Verfahrens.
     *
     * @param list<int> $variantIds
     */
    private function carryOver(ValuationMethod $from, ValuationMethod $to, Organization $organization, array $variantIds): int {
        if ($from === $to || $variantIds === []) {
            return 0;
        }
        if ($from === ValuationMethod::MovingAverage) {
            return $this->backfill->backfill($organization, $variantIds);
        }
        if ($to === ValuationMethod::MovingAverage) {
            // Der Durchschnitt stand still, solange Schichten führten: sein alter Bestand gilt nicht mehr.
            StockValuation::query()->withoutGlobalScopes()
                ->where('organization_id', $organization->id)
                ->whereIn('article_variant_id', $variantIds)
                ->update(['qty_on_hand' => 0]);
        }

        return 0;
    }

    /** @param list<int> $variantIds */
    private function assertSwitchable(ValuationMethod $from, ValuationMethod $to, array $variantIds, string $field): void {
        if ($from === ValuationMethod::MovingAverage || $to !== ValuationMethod::MovingAverage || $variantIds === []) {
            return;
        }

        $layered = StockValuationLayer::query()
            ->whereIn('article_variant_id', $variantIds)
            ->where('qty_remaining', '>', 0)
            ->exists();
        if ($layered) {
            throw ValidationException::withMessages([$field => __('inventory.valuation.switch.blocked_layers')]);
        }
    }

    /**
     * @param \Closure(Builder<Article>): mixed $articles
     * @return list<int>
     */
    private function variantIds(Organization $organization, \Closure $articles): array {
        /** @var list<int> $ids */
        $ids = ArticleVariant::query()->withoutGlobalScopes()
            ->where('organization_id', $organization->id)
            ->whereHas('article', $articles)
            ->pluck('id')->map(fn ($id): int => (int) $id)->all();

        return $ids;
    }
}
