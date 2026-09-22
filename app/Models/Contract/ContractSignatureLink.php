<?php
/*
 * Created on   : Mon Sep 21 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ContractSignatureLink.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Models\Contract;

use App\Enums\Contract\SignatureLinkPurpose;
use App\Models\Concerns\{BelongsToOrganization, HasSqid};
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Öffentlicher Link einer Vertragsfassung (Feature 157): nur der SHA-256-Hash
 * des Tokens wird gespeichert, der Klartext überlebt genau eine Anzeige.
 * Signaturlinks sind Einmal-Tokens (used_at); Abruflinks bleiben bis zum
 * Ablauf oder Widerruf mehrfach nutzbar.
 *
 * @property int $id
 * @property int $organization_id
 * @property int $revision_id
 * @property int|null $request_id
 * @property SignatureLinkPurpose $purpose
 * @property string $token_hash
 * @property \Illuminate\Support\Carbon $expires_at
 * @property \Illuminate\Support\Carbon|null $opened_at
 * @property \Illuminate\Support\Carbon|null $used_at
 * @property \Illuminate\Support\Carbon|null $revoked_at
 * @property \Illuminate\Support\Carbon|null $sent_at
 * @property string|null $sent_to
 * @property string|null $send_error
 */
class ContractSignatureLink extends Model {
    use BelongsToOrganization;
    use HasSqid;

    protected $fillable = [
        'organization_id', 'revision_id', 'request_id', 'purpose', 'token_hash', 'expires_at',
        'opened_at', 'used_at', 'revoked_at', 'revoked_by', 'sent_at', 'sent_to', 'send_error', 'created_by',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'purpose' => SignatureLinkPurpose::class,
        'expires_at' => 'datetime',
        'opened_at' => 'datetime',
        'used_at' => 'datetime',
        'revoked_at' => 'datetime',
        'sent_at' => 'datetime',
    ];

    /** @var list<string> Token-Hash nie in Logs/Audit. */
    protected $hidden = ['token_hash'];

    /** @return BelongsTo<ContractSigningRevision, $this> */
    public function revision(): BelongsTo {
        return $this->belongsTo(ContractSigningRevision::class, 'revision_id');
    }

    /** @return BelongsTo<ContractSignatureRequest, $this> */
    public function request(): BelongsTo {
        return $this->belongsTo(ContractSignatureRequest::class, 'request_id');
    }

    /** @return BelongsTo<User, $this> */
    public function creator(): BelongsTo {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function isUsable(): bool {
        return $this->used_at === null && $this->revoked_at === null && $this->expires_at->isFuture();
    }

    /** Anzeigestand des Links — die Reihenfolge entscheidet über die Lesart. */
    public function stateKey(): string {
        return match (true) {
            $this->used_at !== null => 'used',
            $this->revoked_at !== null => 'revoked',
            $this->expires_at->isPast() => 'expired',
            default => 'active',
        };
    }
}
