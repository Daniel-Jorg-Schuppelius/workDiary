<?php
/*
 * Created on   : Tue Sep 29 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : IdentifierAuditModels.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Stammdaten;

use Illuminate\Database\Eloquent\Model;

/** Modelle mit Kennungen (IBAN, USt-IdNr., GTIN …) für `identifiers:audit`; Plugins tragen ihre ein (MVP-1044). */
final class IdentifierAuditModels {
    /** @var list<class-string<Model>> */
    private array $models = [
        \App\Models\Customer\Customer::class,
        \App\Models\Supplier\Supplier::class,
        \App\Models\Platform\User::class,
        \App\Models\Finance\BankAccount::class,
        \App\Models\Contacts\ContactBankAccount::class,
        \App\Models\Article\Article::class,
        \App\Models\Article\ArticleVariant::class,
        \App\Models\Supplier\SupplierCatalogItem::class,
    ];

    /** @param  class-string<Model>  $model */
    public function register(string $model): void {
        if (! in_array($model, $this->models, true)) {
            $this->models[] = $model;
        }
    }

    /** @return list<class-string<Model>> */
    public function all(): array {
        return $this->models;
    }
}
