<?php
/*
 * Created on   : Thu Aug 21 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : SepaMandate.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Models\Finance;

use App\Enums\Finance\{MandateKind, MandateStatus};
use App\Models\Concerns\{Auditable, BelongsToOrganization, HasSqid};
use App\Models\Customer;
use Carbon\CarbonImmutable;
use CommonToolkit\Helper\Data\BankHelper;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * SEPA-Lastschriftmandat (Feature 120, MVP-609).
 *
 * Die IBAN liegt wie beim eigenen Bankkonto verschlüsselt at-rest; `iban_hash`
 * dient dem Wiederfinden ohne Entschlüsselung.
 *
 * @property int $id
 * @property string $reference
 * @property MandateKind $kind
 * @property MandateStatus $status
 * @property Carbon|null $signed_on
 * @property Carbon|null $last_collected_on
 * @property Carbon|null $revoked_on
 * @property string|null $iban
 * @property string|null $bic
 */
class SepaMandate extends Model {
    use Auditable;
    use BelongsToOrganization;
    /** @use HasFactory<\Database\Factories\Finance\SepaMandateFactory> */
    use HasFactory;
    use HasSqid;

    /** Nach 36 Monaten ohne Einzug verfällt ein Mandat (SEPA-Regelwerk). */
    public const DORMANT_MONTHS = 36;

    protected $fillable = [
        'organization_id', 'customer_id', 'reference', 'kind', 'status',
        'signed_on', 'last_collected_on', 'revoked_on',
        'iban', 'iban_hash', 'bic', 'account_holder', 'note',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'kind' => MandateKind::class,
        'status' => MandateStatus::class,
        'signed_on' => 'date',
        'last_collected_on' => 'date',
        'revoked_on' => 'date',
        'iban' => 'encrypted',
        'bic' => 'encrypted',
        'account_holder' => 'encrypted',
    ];

    protected static function booted(): void {
        static::saving(function (self $mandate): void {
            $mandate->iban_hash = (string) BankHelper::hashIBAN($mandate->iban);
        });
    }

    /** @return BelongsTo<Customer, $this> */
    public function customer(): BelongsTo {
        return $this->belongsTo(Customer::class);
    }

    /**
     * Einziehbar? Ein widerrufenes Mandat nie, ein 36 Monate unbenutztes auch
     * nicht — die Bank weist den Einzug sonst zurück.
     */
    public function isUsable(?CarbonImmutable $on = null): bool {
        if ($this->status !== MandateStatus::Active) {
            return false;
        }
        $reference = $on ?? CarbonImmutable::now();
        $last = $this->last_collected_on;
        if ($last === null) {
            return true;
        }

        return CarbonImmutable::parse($last)->addMonths(self::DORMANT_MONTHS)->greaterThanOrEqualTo($reference);
    }

    /** Erst- oder Folgelastschrift — bestimmt die Vorlauffrist. */
    public function isFirstCollection(): bool {
        return $this->last_collected_on === null;
    }
}
