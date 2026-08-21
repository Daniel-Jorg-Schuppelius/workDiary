<?php
/*
 * Created on   : Thu Aug 21 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : PaymentRun.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Models\Finance;

use App\Enums\Finance\{PaymentRunKind, PaymentRunStatus};
use App\Models\Concerns\{Auditable, BelongsToOrganization, HasSqid};
use App\Models\{Document, User};
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\{BelongsTo, HasMany};
use Illuminate\Support\Carbon;

/**
 * Zahllauf (Feature 120, MVP-609) — Sammelüberweisung oder Sammeleinzug.
 *
 * workDiary erzeugt eine DATEI, keinen Zahlungsauftrag: Die Autorisierung
 * bleibt im Banking-Programm. Der Lauf selbst ist der revisionsrelevante
 * Vorgang und wird nach dem Export nicht mehr verändert.
 *
 * @property PaymentRunKind $kind
 * @property PaymentRunStatus $status
 * @property Carbon|null $execution_date
 * @property Carbon|null $released_at
 * @property Carbon|null $exported_at
 * @property string|null $message_id
 * @property string|null $file_sha256
 */
class PaymentRun extends Model {
    use Auditable;
    use BelongsToOrganization;
    use HasSqid;

    protected $fillable = [
        'organization_id', 'bank_account_id', 'kind', 'status', 'label',
        'execution_date', 'message_id', 'currency', 'total',
        'created_by', 'released_by', 'released_at', 'exported_at',
        'document_id', 'file_sha256',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'kind' => PaymentRunKind::class,
        'status' => PaymentRunStatus::class,
        'execution_date' => 'date',
        'released_at' => 'datetime',
        'exported_at' => 'datetime',
        'currency' => \CommonToolkit\Enums\CurrencyCode::class,
        'total' => 'decimal:2',
    ];

    /** @var array<string, mixed> */
    protected $attributes = ['kind' => 'credit_transfer', 'status' => 'draft', 'currency' => 'EUR'];

    /** @return HasMany<PaymentRunItem, $this> */
    public function items(): HasMany {
        return $this->hasMany(PaymentRunItem::class);
    }

    /** @return BelongsTo<BankAccount, $this> */
    public function bankAccount(): BelongsTo {
        return $this->belongsTo(BankAccount::class);
    }

    /** @return BelongsTo<Document, $this> */
    public function document(): BelongsTo {
        return $this->belongsTo(Document::class);
    }

    /** @return BelongsTo<User, $this> */
    public function releasedBy(): BelongsTo {
        return $this->belongsTo(User::class, 'released_by');
    }

    public function isDraft(): bool {
        return $this->status === PaymentRunStatus::Draft;
    }

    public function isReleased(): bool {
        return $this->status === PaymentRunStatus::Released;
    }

    public function isExported(): bool {
        return $this->status === PaymentRunStatus::Exported;
    }
}
