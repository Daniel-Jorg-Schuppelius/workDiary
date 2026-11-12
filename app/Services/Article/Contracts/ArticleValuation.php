<?php
/*
 * Created on   : Tue Nov 10 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ArticleValuation.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Article\Contracts;

use App\Enums\Inventory\ValuationMethod;
use App\Models\Article\Article;
use App\Models\Platform\Organization;
use Closure;
use Illuminate\Validation\ValidationException;

/**
 * Bewertungsverfahren am Artikel (MVP-1579): der Artikelstamm kennt das Feld,
 * den Bestand dahinter führt das Lager. Ohne Lager-Modul gibt es keinen Bestand
 * zu übertragen ({@see NullArticleValuation}).
 */
interface ArticleValuation {
    /** Verfahren der Organisation — die Vorgabe „wie Organisation“ im Artikelformular. */
    public function organizationMethod(Organization $organization): ValuationMethod;

    /**
     * Speichert den Artikel über `$save` und begleitet einen Verfahrenswechsel:
     * Prüfung davor, Übertrag des Bestands danach, beides in einer Transaktion.
     *
     * @param Closure(): void $save
     * @param array<string, mixed> $attributes zu speichernde Felder; ohne `valuation_method` bleibt das Verfahren
     *
     * @throws ValidationException wenn der Wechsel bei vorhandenem Bestand nicht zulässig ist
     */
    public function saveArticle(Article $article, array $attributes, Organization $organization, Closure $save): void;
}
