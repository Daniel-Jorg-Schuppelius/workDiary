<?php
/*
 * Created on   : Mon Nov 02 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : AccountingArticle.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Models\Finance;

use App\Casts\{MoneyCast, PercentageCast};
use App\Models\Concerns\{BelongsToOrganization, HasSqid};
use Illuminate\Database\Eloquent\{Builder, Model};
use Illuminate\Support\Carbon;

/**
 * Gespiegelter Artikel eines Buchhaltungsprogramms ohne eigenen Artikel-Sync
 * (Feature 185, gap-09): eine Tabelle für easybill, sevDesk, Invoice Ninja,
 * Akaunting und InvoicePlane. Der Katalogschlüssel ist `<präfix>:<id>` der
 * jeweiligen {@see \App\Services\Finance\Accounting\Articles\AccountingArticleCatalogSource}.
 *
 * @property int $id
 * @property int $organization_id
 * @property string $plugin_id
 * @property string $external_id
 * @property string|null $number
 * @property string $name
 * @property string|null $description
 * @property string|null $unit_name
 * @property \CommonToolkit\ValueObjects\Money|null $net_unit_price
 * @property \CommonToolkit\Enums\CurrencyCode $currency
 * @property \CommonToolkit\ValueObjects\Percentage|null $vat_rate
 * @property string $kind
 * @property Carbon|null $archived_at
 * @property Carbon|null $synced_at
 */
class AccountingArticle extends Model {
    use BelongsToOrganization;
    use HasSqid;

    protected $table = 'accounting_articles';

    protected $fillable = [
        'organization_id', 'plugin_id', 'external_id', 'number', 'name', 'description', 'unit_name',
        'net_unit_price', 'currency', 'vat_rate', 'kind', 'archived_at', 'synced_at',
    ];

    protected $casts = [
        'currency' => \CommonToolkit\Enums\CurrencyCode::class,
        'net_unit_price' => MoneyCast::class . ':currency,4',
        'vat_rate' => PercentageCast::class . ':2',
        'archived_at' => 'datetime',
        'synced_at' => 'datetime',
    ];

    /**
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeActive(Builder $query): Builder {
        return $query->whereNull('archived_at');
    }
}
