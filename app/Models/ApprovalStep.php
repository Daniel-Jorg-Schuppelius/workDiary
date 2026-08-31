<?php
/*
 * Created on   : Thu Aug 13 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ApprovalStep.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Models;

use App\Enums\Approval\ApprovalDecision;
use App\Models\Concerns\{AppendOnly, BelongsToOrganization, HasSqid};
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\{BelongsTo, MorphTo};

/**
 * MVP-531: eine entschiedene Genehmigungsstufe eines Antrags (append-only
 * Journal — Schritte werden nie geändert oder gelöscht; Korrektur = neuer
 * Antrag). Kein Auditable: die Fach-Flows auditieren ihre Entscheidungen
 * selbst, das Journal IST die Historie.
 *
 * @property int $id
 * @property int $organization_id
 * @property string $approvable_type
 * @property int $approvable_id
 * @property int $stage 1-basiert
 * @property ApprovalDecision $decision
 * @property int|null $decided_by
 * @property string|null $comment
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 */
class ApprovalStep extends Model {
    // Append-only (Sicherheitsscan 2026-08-23, S-59): der Docblock sagte das
    // seit MVP-531, durchgesetzt wurde es nicht. Eine Freigabestufe ist ein
    // Nachweis, wer wann entschieden hat — sie nachträglich zu ändern hieße,
    // die Entscheidung umzuschreiben.
    use AppendOnly;
    use BelongsToOrganization;
    use HasSqid;

    protected $fillable = [
        'organization_id',
        'approvable_type',
        'approvable_id',
        'stage',
        'decision',
        'decided_by',
        'comment',
        // Nur für den Nachtrag einer Altentscheidung
        // ({@see ApprovalFlowService::backfillStage()}): der Zeitpunkt muss
        // beim Anlegen gesetzt werden, weil nachträgliches Ändern seit S-59
        // am AppendOnly-Guard scheitert.
        'created_at',
        'updated_at',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'stage' => 'integer',
        'decision' => ApprovalDecision::class,
    ];

    /** @return MorphTo<Model, $this> */
    public function approvable(): MorphTo {
        return $this->morphTo();
    }

    /** @return BelongsTo<User, $this> */
    public function decidedBy(): BelongsTo {
        return $this->belongsTo(User::class, 'decided_by');
    }
}
