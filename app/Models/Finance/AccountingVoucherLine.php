<?php
/*
 * Created on   : Mon Oct 26 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : AccountingVoucherLine.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Models\Finance;

use App\Models\Concerns\{BelongsToOrganization, HasSqid};
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Position eines gespiegelten Belegs (Feature 185, MVP-1424) — gleiche
 * Spalten wie `lexoffice_voucher_lines`, in place über `(voucher_id, position)`.
 * `article_ref` ist der Katalogschlüssel, wenn der Puller ihn kennt.
 *
 * @property int $id
 * @property int $organization_id
 * @property int $voucher_id
 * @property int $position
 * @property string|null $external_article_id
 * @property string|null $article_ref
 * @property string $name
 * @property string|null $description
 * @property string $quantity
 * @property string|null $unit_name
 * @property string $unit_net
 * @property string $total_net
 * @property string|null $tax_rate
 * @property string $currency
 * @property-read AccountingVoucher $voucher
 */
class AccountingVoucherLine extends Model {
    use BelongsToOrganization;
    use HasSqid;

    protected $table = 'accounting_voucher_lines';

    protected $fillable = [
        'organization_id', 'voucher_id', 'position', 'external_article_id', 'article_ref', 'name', 'description',
        'quantity', 'unit_name', 'unit_net', 'total_net', 'tax_rate', 'currency',
    ];

    protected $casts = [
        'position' => 'integer',
        'quantity' => 'decimal:3',
        'unit_net' => 'decimal:4',
        'total_net' => 'decimal:2',
        'tax_rate' => 'decimal:2',
    ];

    /** @return BelongsTo<AccountingVoucher, $this> */
    public function voucher(): BelongsTo {
        return $this->belongsTo(AccountingVoucher::class, 'voucher_id');
    }
}
