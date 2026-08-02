<?php
/*
 * Created on   : Sun Aug 02 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : DisposalHandover.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Models\Disposal;

use App\Enums\Disposal\DisposalProofType;
use App\Models\Concerns\HasSqid;
use App\Models\{Document, ExternalContact, User};
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Entsorger-Übergabe einer Entsorgungsakte (Feature 100, MVP-470): externer
 * Entsorgungsfachbetrieb (ExternalContact, Feature 033), Nachweistyp mit
 * Belegnummer, optionaler DMS-Beleg und EfbV-Zertifikat-Referenz. Für
 * gefährliche Abfälle (`*`-AVV-Schlüssel) ist mindestens eine Übergabe
 * Pflicht vor dem Abschluss. Mandantengrenze transitiv über disposal_jobs.
 *
 * @property int $id
 * @property int $disposal_job_id
 * @property int $external_contact_id
 * @property DisposalProofType $proof_type
 * @property string $document_number
 * @property \Illuminate\Support\Carbon $handed_over_on
 * @property int|null $document_id
 * @property string|null $certificate_reference
 * @property string|null $note
 * @property int $created_by_user_id
 */
class DisposalHandover extends Model {
    use HasSqid;

    protected $fillable = [
        'disposal_job_id', 'external_contact_id', 'proof_type',
        'document_number', 'handed_over_on', 'document_id',
        'certificate_reference', 'note', 'created_by_user_id',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'proof_type' => DisposalProofType::class,
        'handed_over_on' => 'date',
    ];

    /** @return BelongsTo<DisposalJob, $this> */
    public function job(): BelongsTo {
        return $this->belongsTo(DisposalJob::class, 'disposal_job_id');
    }

    /** @return BelongsTo<ExternalContact, $this> */
    public function disposer(): BelongsTo {
        return $this->belongsTo(ExternalContact::class, 'external_contact_id');
    }

    /** @return BelongsTo<Document, $this> */
    public function document(): BelongsTo {
        return $this->belongsTo(Document::class);
    }

    /** @return BelongsTo<User, $this> */
    public function creator(): BelongsTo {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }
}
