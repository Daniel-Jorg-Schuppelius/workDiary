<?php
/*
 * Created on   : Sat Jun 13 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : BankAccount.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Models\Finance;

use App\Models\Concerns\{Auditable, BelongsToOrganization, HasSqid};
use CommonToolkit\Helper\Data\BankHelper;
use Database\Factories\Finance\BankAccountFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\{Model, SoftDeletes};
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Eigenes Bankkonto der Organisation (Feature 045, „Priorität 3").
 *
 * Die IBAN liegt verschlüsselt at-rest; `iban_hash` (SHA-256 der normalisierten
 * IBAN, plaintext) wird beim Speichern automatisch synchron gehalten und dient
 * der Eindeutigkeit sowie der Auto-Zuordnung eingehender Auszüge.
 *
 * @property int $id
 * @property int $organization_id
 * @property string $label
 * @property string $iban
 * @property string $iban_hash
 * @property string|null $bic
 * @property string|null $account_holder
 * @property string|null $datev_account_no
 * @property bool $is_active
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class BankAccount extends Model {
    use Auditable;
    use BelongsToOrganization;

    /** @use HasFactory<BankAccountFactory> */
    use HasFactory;

    use HasSqid;
    use SoftDeletes;

    protected $fillable = [
        'organization_id',
        'label',
        'iban',
        'iban_hash',
        'bic',
        'account_holder',
        'datev_account_no',
        'is_active',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'iban' => 'encrypted',
        'bic' => 'encrypted',
        'account_holder' => 'encrypted',
        'is_active' => 'boolean',
    ];

    protected static function booted(): void {
        static::saving(function (self $account): void {
            // iban_hash IMMER aus der (entschlüsselten) IBAN ableiten — nie
            // aus Klient-Eingabe übernehmen.
            $account->iban_hash = (string) BankHelper::hashIBAN($account->iban);
        });
    }

    /** @return HasMany<BankStatement, $this> */
    public function statements(): HasMany {
        return $this->hasMany(BankStatement::class);
    }
}
