<?php
/*
 * Created on   : Sun Sep 27 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : DamageCase.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Models\Damage;

use App\Enums\Damage\{DamageCaseStatus, DamageKind};
use App\Models\Concerns\{Auditable, BelongsToOrganization, HasAttachments, HasJournal, HasSqid};
use App\Models\Contracts\DamageCaseSubject;
use App\Models\Platform\User;
use CommonToolkit\Enums\CurrencyCode;
use CommonToolkit\Helper\Data\NumberHelper;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\{BelongsTo, MorphTo};

/**
 * Versicherungs-/Schadensfall (MVP-919) an einem Träger (Verleihvorgang,
 * Leasingvertrag, Reklamation, Fahrzeug). Status nur über
 * {@see \App\Services\Damage\DamageCaseService}; Verlauf im Journal.
 *
 * @property int $id
 * @property int $organization_id
 * @property string|null $number
 * @property string $subject_type
 * @property int $subject_id
 * @property DamageKind $kind
 * @property DamageCaseStatus $status
 * @property string $title
 * @property string|null $description
 * @property \Illuminate\Support\Carbon|null $occurred_at
 * @property \Illuminate\Support\Carbon|null $reported_at
 * @property string|null $insurer_name
 * @property string|null $policy_number
 * @property string|null $claim_number
 * @property string|null $estimated_amount
 * @property string|null $settled_amount
 * @property string|null $deductible_amount
 * @property CurrencyCode $currency
 * @property int|null $responsible_user_id
 * @property int|null $created_by
 * @property int|null $updated_by
 * @property-read (Model&DamageCaseSubject)|null $subject
 */
class DamageCase extends Model {
    use Auditable;
    use BelongsToOrganization;
    use HasAttachments;
    use HasJournal;
    use HasSqid;

    protected static string $journalClass = DamageCaseEvent::class;

    protected $fillable = [
        'organization_id', 'number', 'subject_type', 'subject_id', 'kind', 'status', 'title',
        'description', 'occurred_at', 'reported_at', 'insurer_name', 'policy_number', 'claim_number',
        'estimated_amount', 'settled_amount', 'deductible_amount', 'currency',
        'responsible_user_id', 'created_by', 'updated_by',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'kind' => DamageKind::class,
        'status' => DamageCaseStatus::class,
        'currency' => CurrencyCode::class,
        'occurred_at' => 'datetime',
        'reported_at' => 'datetime',
        'estimated_amount' => 'decimal:2',
        'settled_amount' => 'decimal:2',
        'deductible_amount' => 'decimal:2',
    ];

    /** @return MorphTo<Model, $this> */
    public function subject(): MorphTo {
        return $this->morphTo();
    }

    /** @return BelongsTo<User, $this> */
    public function responsible(): BelongsTo {
        return $this->belongsTo(User::class, 'responsible_user_id');
    }

    /** Erstattung netto nach Selbstbehalt (reguliert − Selbstbehalt, nie negativ). */
    public function netRecovery(): ?string {
        if ($this->settled_amount === null) {
            return null;
        }
        /** @var numeric-string $settled */
        $settled = (string) $this->settled_amount;
        /** @var numeric-string $deductible */
        $deductible = (string) ($this->deductible_amount ?? '0');
        return NumberHelper::maxPrecise(NumberHelper::subtractPrecise($settled, $deductible, 2), '0.00', 2);
    }
}
