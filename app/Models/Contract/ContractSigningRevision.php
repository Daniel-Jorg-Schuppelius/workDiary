<?php
/*
 * Created on   : Mon Sep 21 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ContractSigningRevision.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Models\Contract;

use App\Enums\Contract\{SignatureParty, SigningRevisionStatus};
use App\Models\Concerns\{BelongsToOrganization, HasSqid};
use App\Models\Platform\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\{BelongsTo, HasMany, HasOne};

/**
 * Vertragsfassung / Signaturvorgang einer Kundenvereinbarung (Feature 157,
 * MVP-822). Ab der Bereitstellung eingefroren: Manifest (konkrete
 * Dokumentversionen mit SHA-256), Parteien und Erklärung ändern sich nur noch
 * über eine neue Fassung. Fachlogik im
 * {@see \App\Services\Contract\ContractSigningService}.
 *
 * @property int $id
 * @property int $organization_id
 * @property int $contract_id
 * @property int $revision_no
 * @property int|null $predecessor_id
 * @property SigningRevisionStatus $status
 * @property SignatureParty|null $controller_party
 * @property string $declaration_text
 * @property string|null $manifest_hash
 * @property \Illuminate\Support\Carbon|null $review_on
 * @property \Illuminate\Support\Carbon|null $prepared_at
 * @property \Illuminate\Support\Carbon|null $completed_at
 * @property \Illuminate\Support\Carbon|null $withdrawn_at
 * @property string|null $withdrawal_reason
 * @property int|null $superseded_by_id
 * @property \Illuminate\Support\Carbon|null $superseded_at
 * @property \Illuminate\Support\Carbon|null $effective_on
 * @property \Illuminate\Support\Carbon|null $customer_visible_at
 */
class ContractSigningRevision extends Model {
    use BelongsToOrganization;
    use HasSqid;

    protected $fillable = [
        'organization_id', 'contract_id', 'revision_no', 'predecessor_id', 'status',
        'controller_party', 'declaration_text', 'manifest_hash', 'review_on',
        'prepared_at', 'prepared_by', 'completed_at', 'withdrawn_at', 'withdrawn_by',
        'withdrawal_reason', 'superseded_by_id', 'superseded_at', 'effective_on',
        'customer_visible_at', 'customer_visible_by', 'created_by',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'status' => SigningRevisionStatus::class,
        'controller_party' => SignatureParty::class,
        'revision_no' => 'integer',
        'review_on' => 'date',
        'effective_on' => 'date',
        'prepared_at' => 'datetime',
        'completed_at' => 'datetime',
        'withdrawn_at' => 'datetime',
        'superseded_at' => 'datetime',
        'customer_visible_at' => 'datetime',
    ];

    /** @return BelongsTo<Contract, $this> */
    public function contract(): BelongsTo {
        return $this->belongsTo(Contract::class);
    }

    /** @return BelongsTo<self, $this> */
    public function predecessor(): BelongsTo {
        return $this->belongsTo(self::class, 'predecessor_id');
    }

    /** @return BelongsTo<self, $this> */
    public function supersededBy(): BelongsTo {
        return $this->belongsTo(self::class, 'superseded_by_id');
    }

    /** @return BelongsTo<User, $this> */
    public function preparer(): BelongsTo {
        return $this->belongsTo(User::class, 'prepared_by');
    }

    /** @return BelongsTo<User, $this> */
    public function creator(): BelongsTo {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** @return HasMany<ContractSigningManifestItem, $this> */
    public function manifestItems(): HasMany {
        return $this->hasMany(ContractSigningManifestItem::class, 'revision_id')->orderBy('sort');
    }

    /** @return HasMany<ContractSignatureRequest, $this> */
    public function requests(): HasMany {
        return $this->hasMany(ContractSignatureRequest::class, 'revision_id')->orderBy('id');
    }

    /** @return HasOne<ContractSignatureRequest, $this> */
    public function customerRequest(): HasOne {
        return $this->hasOne(ContractSignatureRequest::class, 'revision_id')->where('party', SignatureParty::Customer->value);
    }

    /** @return HasOne<ContractSignatureRequest, $this> */
    public function organizationRequest(): HasOne {
        return $this->hasOne(ContractSignatureRequest::class, 'revision_id')->where('party', SignatureParty::Organization->value);
    }

    /** @return HasMany<ContractSignatureLink, $this> */
    public function links(): HasMany {
        return $this->hasMany(ContractSignatureLink::class, 'revision_id')->orderByDesc('id');
    }

    /** @return HasMany<ContractSignatureEvidence, $this> */
    public function evidences(): HasMany {
        return $this->hasMany(ContractSignatureEvidence::class, 'revision_id')->orderBy('id');
    }

    /** Fassung ist im Portal freigegeben (nur unterzeichnete Fassungen). */
    public function isReleasedToCustomer(): bool {
        return $this->customer_visible_at !== null && $this->status->isSigned();
    }

    /** Anzeigename „Fassung n" — kein eigener Titel, der Vertrag trägt ihn. */
    public function label(): string {
        return (string) __('contract-signing.revision.label', ['no' => $this->revision_no]);
    }
}
