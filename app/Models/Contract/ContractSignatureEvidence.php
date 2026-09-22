<?php
/*
 * Created on   : Mon Sep 21 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ContractSignatureEvidence.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Models\Contract;

use App\Enums\Contract\{EvidenceReviewStatus, SignatureMethod, SignatureParty};
use App\Models\Concerns\{BelongsToOrganization, HasSqid};
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use RuntimeException;

/**
 * Unterzeichnungsnachweis (Feature 157): gezeichnete/getippte Browser-Signatur
 * oder hochgeladenes PDF, gebunden an Fassung und Manifest-Hash. Der Nachweis
 * selbst ist unveränderlich; nur die Prüfentscheidung (review_*) wird genau
 * einmal nachgetragen. Falsche Zuordnungen bekommen einen neuen Nachweis,
 * keinen stillen Austausch.
 *
 * @property int $id
 * @property int $organization_id
 * @property int $revision_id
 * @property int $request_id
 * @property int|null $link_id
 * @property string $manifest_hash
 * @property SignatureParty $party
 * @property SignatureMethod $method
 * @property string $submitted_via
 * @property string $signer_name
 * @property string|null $signer_function
 * @property string $declaration_text
 * @property bool $declaration_accepted
 * @property bool $authority_confirmed
 * @property \Illuminate\Support\Carbon $signed_at
 * @property \Illuminate\Support\Carbon|null $stated_signed_on
 * @property string|null $disk
 * @property string|null $path
 * @property string|null $original_name
 * @property string|null $mime
 * @property int $size
 * @property string|null $file_hash
 * @property int|null $recorded_by_user_id
 * @property EvidenceReviewStatus|null $review_status
 * @property int|null $reviewed_by_user_id
 * @property \Illuminate\Support\Carbon|null $reviewed_at
 * @property string|null $review_note
 */
class ContractSignatureEvidence extends Model {
    use BelongsToOrganization;
    use HasSqid;

    // „evidence" ist im Pluralizer unzählbar — der Tabellenname trägt trotzdem das -s.
    protected $table = 'contract_signature_evidences';

    public const VIA_LINK = 'link';

    public const VIA_INTERNAL = 'internal';

    /** @var list<string> Die einzigen nachträglich beschreibbaren Spalten (Prüfentscheidung). */
    private const REVIEW_COLUMNS = ['review_status', 'reviewed_by_user_id', 'reviewed_at', 'review_note', 'updated_at'];

    protected $fillable = [
        'organization_id', 'revision_id', 'request_id', 'link_id', 'manifest_hash', 'party', 'method',
        'submitted_via', 'signer_name', 'signer_function', 'declaration_text', 'declaration_accepted',
        'authority_confirmed', 'signed_at', 'stated_signed_on', 'disk', 'path', 'original_name', 'mime',
        'size', 'file_hash', 'recorded_by_user_id', 'review_status', 'reviewed_by_user_id', 'reviewed_at',
        'review_note', 'ip', 'user_agent',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'party' => SignatureParty::class,
        'method' => SignatureMethod::class,
        'review_status' => EvidenceReviewStatus::class,
        'declaration_accepted' => 'boolean',
        'authority_confirmed' => 'boolean',
        'signed_at' => 'datetime',
        'stated_signed_on' => 'date',
        'reviewed_at' => 'datetime',
        'size' => 'integer',
    ];

    protected static function booted(): void {
        static::updating(function (self $evidence): void {
            $illegal = array_diff(array_keys($evidence->getDirty()), self::REVIEW_COLUMNS);
            if ($illegal !== []) {
                throw new RuntimeException(self::class . ' ist unveränderlich (nur die Prüfentscheidung wird nachgetragen): ' . implode(', ', $illegal));
            }
            if ($evidence->getOriginal('review_status') !== null && $evidence->getOriginal('review_status') !== EvidenceReviewStatus::Pending) {
                throw new RuntimeException(self::class . ': die Prüfentscheidung wird genau einmal getroffen.');
            }
        });
        static::deleting(function (): void {
            throw new RuntimeException(self::class . ' ist append-only und darf nicht gelöscht werden.');
        });
    }

    /** @return BelongsTo<ContractSigningRevision, $this> */
    public function revision(): BelongsTo {
        return $this->belongsTo(ContractSigningRevision::class, 'revision_id');
    }

    /** @return BelongsTo<ContractSignatureRequest, $this> */
    public function request(): BelongsTo {
        return $this->belongsTo(ContractSignatureRequest::class, 'request_id');
    }

    /** @return BelongsTo<User, $this> */
    public function recorder(): BelongsTo {
        return $this->belongsTo(User::class, 'recorded_by_user_id');
    }

    /** @return BelongsTo<User, $this> */
    public function reviewer(): BelongsTo {
        return $this->belongsTo(User::class, 'reviewed_by_user_id');
    }

    public function isPendingReview(): bool {
        return $this->review_status === EvidenceReviewStatus::Pending;
    }

    /** Browser-Signatur oder bestätigter Papiernachweis — erfüllt die Anforderung. */
    public function countsAsSignature(): bool {
        return $this->review_status === null || $this->review_status === EvidenceReviewStatus::Accepted;
    }

    public function hasFile(): bool {
        return $this->disk !== null && $this->path !== null;
    }
}
