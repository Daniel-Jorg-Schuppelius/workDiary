<?php
/*
 * Created on   : Wed Oct 07 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : CustomerIntake.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Models\Customer;

use App\Casts\FieldDocumentCast;
use App\Enums\Customer\{IntakeKind, IntakeStatus};
use App\Models\Asset\Asset;
use App\Models\Concerns\{Auditable, BelongsToOrganization, HasAttachments, HasJournal, HasSqid};
use App\Models\Platform\User;
use App\Models\Procurement\RequestItem;
use App\Models\Sales\Quote;
use App\Services\Fields\FieldDocument;
use Illuminate\Database\Eloquent\{Builder, Model};
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\{BelongsTo, HasMany, MorphTo};
use Illuminate\Support\Carbon;

/**
 * Kundeneingang aus dem Portal (Feature 162, MVP-1074–1077): schmale Akte vor
 * Angebot und Fachakte. Eingefrorene Formularangaben, Dateien (Anhänge),
 * Rückfragen, Angebot und Übernahmeziel bleiben hier zusammen auffindbar.
 *
 * @property int $id
 * @property int $organization_id
 * @property int $customer_id
 * @property int|null $submitted_by_user_id
 * @property string $number
 * @property IntakeKind $kind
 * @property IntakeStatus $status
 * @property string $subject
 * @property string|null $description
 * @property Carbon|null $desired_date
 * @property FieldDocument|null $form
 * @property FieldDocument|null $catalog_form
 * @property int|null $request_item_id
 * @property int|null $asset_id
 * @property int|null $assigned_user_id
 * @property int|null $quote_id
 * @property string|null $target_type
 * @property int|null $target_id
 * @property Carbon|null $handed_over_at
 * @property int|null $handover_user_id
 * @property string|null $rejection_reason
 * @property Carbon|null $closed_at
 * @property bool $is_upload_open
 * @property string $submission_key
 * @property Carbon|null $mail_failed_at
 * @property Carbon|null $created_at
 */
class CustomerIntake extends Model {
    use Auditable;
    use BelongsToOrganization;
    use HasAttachments;
    /** @use HasFactory<\Database\Factories\Customer\CustomerIntakeFactory> */
    use HasFactory;
    use HasJournal;
    use HasSqid;

    protected static string $journalClass = CustomerIntakeEvent::class;

    protected $fillable = [
        'organization_id', 'customer_id', 'submitted_by_user_id', 'number', 'kind', 'status',
        'subject', 'description', 'desired_date', 'form', 'catalog_form', 'request_item_id',
        'asset_id', 'assigned_user_id', 'quote_id', 'target_type', 'target_id', 'handed_over_at',
        'handover_user_id', 'rejection_reason', 'closed_at', 'is_upload_open', 'submission_key',
        'mail_failed_at',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'kind' => IntakeKind::class,
        'status' => IntakeStatus::class,
        'desired_date' => 'date',
        'form' => FieldDocumentCast::class,
        'catalog_form' => FieldDocumentCast::class,
        'handed_over_at' => 'datetime',
        'closed_at' => 'datetime',
        'is_upload_open' => 'boolean',
        'mail_failed_at' => 'datetime',
    ];

    /** @var array<string, mixed> */
    protected $attributes = ['status' => IntakeStatus::Submitted->value, 'is_upload_open' => false];

    /** @return BelongsTo<Customer, $this> */
    public function customer(): BelongsTo {
        return $this->belongsTo(Customer::class);
    }

    /** @return BelongsTo<User, $this> */
    public function submitter(): BelongsTo {
        return $this->belongsTo(User::class, 'submitted_by_user_id');
    }

    /** @return BelongsTo<User, $this> */
    public function assignee(): BelongsTo {
        return $this->belongsTo(User::class, 'assigned_user_id');
    }

    /** @return BelongsTo<User, $this> */
    public function handoverUser(): BelongsTo {
        return $this->belongsTo(User::class, 'handover_user_id');
    }

    /** @return BelongsTo<Quote, $this> */
    public function quote(): BelongsTo {
        return $this->belongsTo(Quote::class);
    }

    /** @return BelongsTo<RequestItem, $this> */
    public function requestItem(): BelongsTo {
        return $this->belongsTo(RequestItem::class);
    }

    /** @return BelongsTo<Asset, $this> */
    public function asset(): BelongsTo {
        return $this->belongsTo(Asset::class);
    }

    /** @return MorphTo<Model, $this> */
    public function target(): MorphTo {
        return $this->morphTo();
    }

    /** @return HasMany<CustomerIntakeUploadLink, $this> */
    public function uploadLinks(): HasMany {
        return $this->hasMany(CustomerIntakeUploadLink::class)->orderByDesc('id');
    }

    /** @return HasMany<CustomerIntakeMessage, $this> */
    public function messages(): HasMany {
        return $this->hasMany(CustomerIntakeMessage::class)->orderBy('created_at')->orderBy('id');
    }

    /**
     * Portal-Grenze: nur Eingänge des eigenen Kunden in der eigenen
     * Organisation — ohne Org-Scope, weil der Portal-Guard keinen setzt.
     *
     * @param  Builder<self>  $query
     */
    public function scopeOfPortalUser(Builder $query, User $portalUser): void {
        $query->withoutGlobalScopes()
            ->where('organization_id', (int) $portalUser->organization_id)
            ->where('customer_id', (int) $portalUser->customer_id);
    }

    /** Darf der Kunde Dateien nachreichen? Offen oder nach Übernahme mit ausdrücklich geöffnetem Kanal. */
    public function acceptsCustomerFiles(): bool {
        return $this->status->isOpen()
            || ($this->status === IntakeStatus::HandedOver && $this->is_upload_open);
    }

    /** Rücknahme nur vor der Beauftragung: offen und ohne angenommenes Angebot. */
    public function canBeWithdrawn(): bool {
        return $this->status->isOpen() && ! ($this->quote?->status->isWon() ?? false);
    }
}
