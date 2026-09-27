<?php
/*
 * Created on   : Sun Sep 27 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : SupplierQuestionnaireRequest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Models\Supplier;

use App\Casts\{FieldSchemaCast, FieldValuesCast};
use App\Enums\Supplier\SupplierQuestionnaireStatus;
use App\Models\Concerns\{Auditable, BelongsToOrganization, HasSqid};
use App\Services\Fields\{FieldSchema, FieldValues};
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Anfrage einer Selbstauskunft an einen Lieferanten (MVP-937): Fragen
 * eingefroren, Antworten als Feldwerte, Link nur als Hash.
 *
 * @property int $id
 * @property int $organization_id
 * @property int $supplier_questionnaire_id
 * @property int $supplier_id
 * @property SupplierQuestionnaireStatus $status
 * @property string $token_hash
 * @property string $recipient_email
 * @property FieldSchema $schema_snapshot
 * @property FieldValues $answers
 * @property Carbon|null $sent_at
 * @property Carbon $expires_at
 * @property Carbon|null $submitted_at
 * @property Carbon|null $valid_until
 * @property int|null $reviewed_by
 * @property Carbon|null $reviewed_at
 * @property string|null $note
 * @property int|null $created_by
 */
class SupplierQuestionnaireRequest extends Model {
    use Auditable;
    use BelongsToOrganization;
    use HasSqid;

    protected $fillable = [
        'organization_id', 'supplier_questionnaire_id', 'supplier_id', 'status', 'token_hash', 'recipient_email',
        'schema_snapshot', 'answers', 'sent_at', 'expires_at', 'submitted_at', 'valid_until', 'reviewed_by', 'reviewed_at', 'note', 'created_by',
    ];

    /** @var list<string> */
    protected $hidden = ['token_hash'];

    /** @var array<string, string> */
    protected $casts = [
        'status' => SupplierQuestionnaireStatus::class,
        'schema_snapshot' => FieldSchemaCast::class,
        'answers' => FieldValuesCast::class,
        'sent_at' => 'datetime',
        'expires_at' => 'datetime',
        'submitted_at' => 'datetime',
        'valid_until' => 'date',
        'reviewed_at' => 'datetime',
    ];

    /** @return BelongsTo<SupplierQuestionnaire, $this> */
    public function questionnaire(): BelongsTo {
        return $this->belongsTo(SupplierQuestionnaire::class, 'supplier_questionnaire_id');
    }

    /** @return BelongsTo<Supplier, $this> */
    public function supplier(): BelongsTo {
        return $this->belongsTo(Supplier::class);
    }
}
