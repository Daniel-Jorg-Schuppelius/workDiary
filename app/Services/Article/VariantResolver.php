<?php
/*
 * Created on   : Tue Jun 16 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : VariantResolver.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Article;

use App\Models\{Article, ArticleOptionValue, ArticleVariant};
use Illuminate\Support\Collection;
use RuntimeException;

/**
 * Bildet und validiert Artikelvarianten (Feature 048, MVP-060). Die
 * Optionskombination wird deterministisch zu einer `option_signature`
 * (sortierte „definition=wert"-Paare) verdichtet; sie ist je Hauptartikel
 * eindeutig. Eine geänderte Kombination erzeugt eine NEUE Variante, statt eine
 * bestehende, bereits verwendete Variante umzudeuten.
 */
class VariantResolver {
    /**
     * Deterministische Signatur aus Optionswerten: sortierte
     * „definitionCode=valueCode"-Paare, mit „|" verbunden.
     *
     * @param  Collection<int, ArticleOptionValue>  $values
     */
    public function signature(Collection $values): string {
        $parts = $values
            ->map(static function (ArticleOptionValue $v): string {
                $definition = $v->definition;
                if ($definition === null) {
                    throw new RuntimeException('Optionswert ohne zugehörige Optionsdefinition.');
                }

                return $definition->code . '=' . $v->code;
            })
            ->sort()
            ->values()
            ->all();

        return implode('|', $parts);
    }

    /**
     * Legt eine Variante mit der gegebenen Optionskombination an. Validiert,
     * dass alle Optionswerte zum Artikel gehören und die Kombination je Artikel
     * eindeutig ist; hängt die Pivot-Werte an.
     *
     * @param  list<int>  $optionValueIds
     * @param  array<string, mixed>  $attributes
     */
    public function createVariant(Article $article, array $optionValueIds, array $attributes = []): ArticleVariant {
        /** @var Collection<int, ArticleOptionValue> $values */
        $values = ArticleOptionValue::query()
            ->with('definition')
            ->whereIn('id', $optionValueIds)
            ->get();

        if ($values->count() !== count(array_unique($optionValueIds))) {
            throw new RuntimeException('Unbekannter oder doppelter Optionswert für die Variante.');
        }

        foreach ($values as $value) {
            $definition = $value->definition;
            if ($definition === null || (int) $definition->article_id !== (int) $article->id) {
                throw new RuntimeException('Optionswert gehört nicht zu diesem Artikel.');
            }
        }

        // Je Optionsdefinition darf höchstens ein Wert gewählt sein.
        $definitionIds = $values->map(static fn(ArticleOptionValue $v): int => (int) $v->article_option_definition_id);
        if ($definitionIds->count() !== $definitionIds->unique()->count()) {
            throw new RuntimeException('Pro Option darf nur ein Wert gewählt werden.');
        }

        $signature = $this->signature($values);

        $exists = ArticleVariant::query()
            ->where('article_id', $article->id)
            ->where('option_signature', $signature)
            ->exists();
        if ($exists) {
            throw new RuntimeException('Diese Optionskombination existiert bereits als Variante.');
        }

        /** @var ArticleVariant $variant */
        $variant = $article->variants()->create(array_merge([
            'organization_id' => $article->organization_id,
            'option_signature' => $signature,
            'name' => $this->composeName($article, $values),
        ], $attributes));

        $variant->optionValues()->sync($optionValueIds);

        return $variant;
    }

    /**
     * Ausgeschriebene Variantenbezeichnung, z. B. „T-Shirt – Rot – M".
     *
     * @param  Collection<int, ArticleOptionValue>  $values
     */
    private function composeName(Article $article, Collection $values): string {
        $suffix = $values
            ->map(static fn(ArticleOptionValue $v): string => $v->label)
            ->implode(' – ');

        return $suffix === '' ? $article->name : $article->name . ' – ' . $suffix;
    }
}
