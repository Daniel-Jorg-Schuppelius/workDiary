<?php
/*
 * Created on   : Thu Aug 20 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : InvoiceRetention.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Models\Invoicing;

use App\Casts\{MoneyCast, PercentageCast};
use App\Enums\Invoicing\{RetentionKind, RetentionStatus};
use App\Models\Concerns\{Auditable, BelongsToOrganization, HasSqid};
use App\Models\{Invoice, User};
use CommonToolkit\ValueObjects\Money;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Sicherheitseinbehalt an einem Beleg (Feature 113, MVP-602).
 *
 * @property int $id
 * @property int $organization_id
 * @property int $invoice_id
 * @property RetentionKind $kind
 * @property \CommonToolkit\ValueObjects\Percentage|null $percent
 * @property Money $base_amount
 * @property Money $amount
 * @property Carbon|null $due_on
 * @property RetentionStatus $status
 * @property Carbon|null $released_on
 * @property string|null $note
 */
class InvoiceRetention extends Model {
    use Auditable;
    use BelongsToOrganization;
    use HasSqid;

    protected $fillable = [
        'organization_id',
        'invoice_id',
        'kind',
        'percent',
        'base_kind',
        'base_amount',
        'amount',
        'currency',
        'due_on',
        'status',
        'released_on',
        'note',
        'created_by',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'kind' => RetentionKind::class,
        'base_kind' => \App\Enums\Invoicing\RetentionBase::class,
        'status' => RetentionStatus::class,
        'percent' => PercentageCast::class . ':2',
        'base_amount' => MoneyCast::class . ':currency',
        'amount' => MoneyCast::class . ':currency',
        'due_on' => 'date',
        'released_on' => 'date',
    ];

    /** @var array<string, mixed> */
    protected $attributes = ['status' => 'open', 'currency' => 'EUR'];

    /** @return BelongsTo<Invoice, $this> */
    public function invoice(): BelongsTo {
        return $this->belongsTo(Invoice::class);
    }

    /** @return BelongsTo<User, $this> */
    public function createdBy(): BelongsTo {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Ist der Freigabetermin überschritten, ohne dass freigegeben wurde?
     * Dann ist der Einbehalt ein ganz normaler offener Posten — und darf
     * gemahnt werden.
     */
    public function isOverdue(): bool {
        return $this->status === RetentionStatus::Open
            && $this->due_on !== null
            && $this->due_on->isPast();
    }
}
