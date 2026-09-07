<?php
/*
 * Created on   : Mon Sep 07 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : LicenseArticleClassifier.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Reselling\Register;

use App\Enums\Reselling\ResaleArticleRole;
use App\Models\LexofficeArticle;
use App\Services\Reselling\Marketplace\ProductNameMatcher;

/**
 * Ist ein Lexoffice-Artikel ein Abo-Produkt? Die Einstufung des Betreibers
 * (`resale_role`) gewinnt; ohne Einstufung entscheidet die Produkterkennung
 * über den Artikelnamen. Einzige Stelle für diese Frage — Vorschlagslauf,
 * Import, Rechnungsliste, Preisprüfung und Positionen-ohne-Abo fragen hier.
 */
final class LicenseArticleClassifier {
    public function __construct(private readonly ProductNameMatcher $matcher = new ProductNameMatcher()) {}

    public function isLicense(?LexofficeArticle $article): bool {
        if ($article === null) {
            return false;
        }
        if ($article->resale_role === ResaleArticleRole::Excluded) {
            return false;
        }
        if ($article->resale_role === ResaleArticleRole::License) {
            return true;
        }

        return $this->matcher->looksLikeMicrosoftProduct((string) $article->name);
    }

    /** Automatische Einstufung ohne Betreiber-Override (für die Anzeige). */
    public function detected(LexofficeArticle $article): ResaleArticleRole {
        return $this->matcher->looksLikeMicrosoftProduct((string) $article->name) ? ResaleArticleRole::License : ResaleArticleRole::Excluded;
    }
}
