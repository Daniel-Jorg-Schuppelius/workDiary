<?php
/*
 * Created on   : Wed Nov 11 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ZeroesRateOfUntaxedCategory.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Http\Requests\Concerns;

use ERechnungToolkit\Enums\TaxCategory;

/**
 * Positionen mit einer Steuerkategorie ohne Steuer (Z, E, AE, K, G, O) tragen
 * 0 % (MVP-1623): Ein leerer Satz fiele auf den Kopfsatz zurück, ein
 * positiver widerspräche der Kategorie — beides hielt erst die E-Rechnungs-
 * Prüfung an.
 */
trait ZeroesRateOfUntaxedCategory {
    protected function prepareForValidation(): void {
        $category = TaxCategory::tryFrom(trim((string) $this->input('tax_category', '')));
        if ($category !== null && ! $category->isTaxable()) {
            $this->merge(['tax_rate' => '0']);
        }
    }
}
