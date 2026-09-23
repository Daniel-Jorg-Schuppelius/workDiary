<?php
/*
 * Created on   : Sun Jun 15 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : CustomerQuery.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Models;

use App\Enums\Customer\CustomerQueryStatus;
use App\Models\Concerns\{Auditable, BelongsToOrganization, HasAttachments, HasSqid};
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\{BelongsTo, MorphTo};

/**
 * Kunden-Rückfrage (Feature 012, Kundenportal & Freigaben).
 *
 * Leichtgewichtiger, nachvollziehbarer Eintrag, den ein Kunde über das
 * Portal bzw. den Signaturlink zu einem vorgelegten Vorgang (Protokoll,
 * Auftrag, Dokument) stellt. Die Organisation wird benachrichtigt und kann
 * intern antworten; die Antwort wird dem Kunden über denselben Kanal
 * angezeigt. Anhänge (MVP-712) hängen als kundensichtbare Attachments am
 * Vorgang und sind wie der Text nach dem Absenden unveränderlich.
 *
 * @property int $id
 * @property int $organization_id
 * @property class-string $subject_type
 * @property int $subject_id
 * @property int|null $customer_id
 * @property int|null $signature_token_id
 * @property string|null $asker_name
 * @property string|null $asker_email
 * @property string $question
 * @property string|null $answer
 * @property CustomerQueryStatus $status
 * @property \Illuminate\Support\Carbon|null $answered_at
 * @property int|null $answered_by_user_id
 */
class CustomerQuery extends Model {
    use Auditable;
    use BelongsToOrganization;
    use HasAttachments;
    use HasSqid;

    protected $fillable = [
        'organization_id',
        'subject_type',
        'subject_id',
        'customer_id',
        'signature_token_id',
        'asker_name',
        'asker_email',
        'question',
        'answer',
        'status',
        'answered_at',
        'answered_by_user_id',
    ];

    protected $casts = [
        'status' => CustomerQueryStatus::class,
        'answered_at' => 'datetime',
    ];

    /** @return MorphTo<Model, $this> */
    public function subject(): MorphTo {
        return $this->morphTo();
    }

    /** @return BelongsTo<Customer, $this> */
    public function customer(): BelongsTo {
        return $this->belongsTo(Customer::class);
    }

    /** @return BelongsTo<ProtocolSignatureToken, $this> */
    public function signatureToken(): BelongsTo {
        return $this->belongsTo(ProtocolSignatureToken::class, 'signature_token_id');
    }

    /** @return BelongsTo<User, $this> */
    public function answeredBy(): BelongsTo {
        return $this->belongsTo(User::class, 'answered_by_user_id');
    }

    public function isOpen(): bool {
        return $this->status === CustomerQueryStatus::Open;
    }
}
