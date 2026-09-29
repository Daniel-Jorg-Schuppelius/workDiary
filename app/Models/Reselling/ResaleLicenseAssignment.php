<?php
/*
 * Created on   : Tue Sep 29 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ResaleLicenseAssignment.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Models\Reselling;

use App\Enums\Reselling\LicenseAssignmentEnd;
use App\Models\Concerns\{Auditable, BelongsToOrganization, HasSqid};
use App\Models\Customer\{Customer, ForeignCustomer};
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Verkauf einer Einzellizenz an einen Kunden (MVP-1024). `active_unit_id` ist
 * gesetzt, solange die Zuordnung gilt; beendete Zuordnungen bleiben als
 * Historie (Rücknahme, Berichtigung).
 *
 * @property int $id
 * @property int $organization_id
 * @property int $unit_id
 * @property int|null $active_unit_id
 * @property int $customer_id
 * @property int|null $foreign_customer_id
 * @property CarbonImmutable $sold_on
 * @property string|null $invoice_reference
 * @property string|null $request_token
 * @property CarbonImmutable|null $ended_at
 * @property LicenseAssignmentEnd|null $end_kind
 * @property string|null $end_reason
 * @property int|null $ended_by_user_id
 * @property int|null $created_by
 * @property int|null $updated_by
 * @property-read ResaleLicenseUnit $unit
 * @property-read Customer $customer
 * @property-read ForeignCustomer|null $foreignCustomer
 */
class ResaleLicenseAssignment extends Model {
    use Auditable;
    use BelongsToOrganization;
    use HasSqid;

    protected $fillable = [
        'organization_id',
        'unit_id',
        'active_unit_id',
        'customer_id',
        'foreign_customer_id',
        'sold_on',
        'invoice_reference',
        'request_token',
        'ended_at',
        'end_kind',
        'end_reason',
        'ended_by_user_id',
        'created_by',
        'updated_by',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'sold_on' => 'immutable_date',
        'ended_at' => 'immutable_datetime',
        'end_kind' => LicenseAssignmentEnd::class,
    ];

    /** @return BelongsTo<ResaleLicenseUnit, $this> */
    public function unit(): BelongsTo {
        return $this->belongsTo(ResaleLicenseUnit::class, 'unit_id');
    }

    /** @return BelongsTo<Customer, $this> */
    public function customer(): BelongsTo {
        return $this->belongsTo(Customer::class);
    }

    /** @return BelongsTo<ForeignCustomer, $this> */
    public function foreignCustomer(): BelongsTo {
        return $this->belongsTo(ForeignCustomer::class);
    }

    public function isActive(): bool {
        return $this->ended_at === null;
    }

    /** Lizenzhalter: der Fremdkunde, sonst der Kunde selbst. */
    public function holderLabel(): string {
        return $this->foreignCustomer->name ?? $this->customer->name;
    }
}
