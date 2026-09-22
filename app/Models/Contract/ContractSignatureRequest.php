<?php
/*
 * Created on   : Mon Sep 21 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ContractSignatureRequest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Models\Contract;

use App\Enums\Contract\{SignatureLinkPurpose, SignatureParty, SignatureRequestStatus};
use App\Models\Concerns\{BelongsToOrganization, HasSqid};
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\{BelongsTo, HasMany};

/**
 * Unterschriftsanforderung je Partei einer Vertragsfassung (Feature 157).
 * Trägt Unterzeichner, Pflicht bzw. begründeten Verzicht und den Stand;
 * Links und Nachweise hängen daran.
 *
 * @property int $id
 * @property int $organization_id
 * @property int $revision_id
 * @property SignatureParty $party
 * @property bool $required
 * @property string $signer_name
 * @property string|null $signer_function
 * @property string|null $signer_email
 * @property SignatureRequestStatus $status
 * @property string|null $waiver_reason
 * @property \Illuminate\Support\Carbon|null $fulfilled_at
 */
class ContractSignatureRequest extends Model {
    use BelongsToOrganization;
    use HasSqid;

    protected $fillable = [
        'organization_id', 'revision_id', 'party', 'required', 'signer_name', 'signer_function',
        'signer_email', 'status', 'waiver_reason', 'fulfilled_at',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'party' => SignatureParty::class,
        'status' => SignatureRequestStatus::class,
        'required' => 'boolean',
        'fulfilled_at' => 'datetime',
    ];

    /** @return BelongsTo<ContractSigningRevision, $this> */
    public function revision(): BelongsTo {
        return $this->belongsTo(ContractSigningRevision::class, 'revision_id');
    }

    /** @return HasMany<ContractSignatureLink, $this> */
    public function links(): HasMany {
        return $this->hasMany(ContractSignatureLink::class, 'request_id')->orderByDesc('id');
    }

    /** @return HasMany<ContractSignatureEvidence, $this> */
    public function evidences(): HasMany {
        return $this->hasMany(ContractSignatureEvidence::class, 'request_id')->orderBy('id');
    }

    /** Aktuell gültiger, noch nicht verbrauchter Signaturlink (höchstens einer). */
    public function activeLink(): ?ContractSignatureLink {
        return $this->links
            ->first(fn (ContractSignatureLink $link): bool => $link->purpose === SignatureLinkPurpose::Sign && $link->isUsable());
    }

    /** Letzter Nachweis, der noch auf die Prüfung wartet. */
    public function pendingEvidence(): ?ContractSignatureEvidence {
        return $this->evidences->last(fn (ContractSignatureEvidence $evidence): bool => $evidence->isPendingReview());
    }

    /** Der Nachweis, der diese Anforderung erfüllt hat. */
    public function fulfillingEvidence(): ?ContractSignatureEvidence {
        return $this->evidences->last(fn (ContractSignatureEvidence $evidence): bool => $evidence->countsAsSignature());
    }
}
