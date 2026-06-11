<?php
/*
 * Created on   : Wed Jun 10 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : FormSubmission.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Models;

use App\Models\Concerns\{Auditable, BelongsToOrganization, HasSqid};
use Database\Factories\FormSubmissionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\{Model, SoftDeletes};
use Illuminate\Database\Eloquent\Relations\{BelongsTo, MorphTo};
use Illuminate\Support\Carbon;

/**
 * Ausgefülltes Formular (Feature 032): Werte plus fields_snapshot —
 * die Felddefinition zum Ausfüllzeitpunkt. Anzeige/Druck laufen IMMER
 * gegen den Snapshot, nie gegen die (ggf. später geänderte) Vorlage.
 * Eigene organization_id: Submissions werden direkt gelistet/gefiltert,
 * transitives Scoping über die Vorlage reicht nicht.
 *
 * @property int $id
 * @property int $organization_id
 * @property int $form_template_id
 * @property list<array{key: string, label: string, type: string, required: bool, options: list<string>, help: string|null, unit: string|null}> $fields_snapshot
 * @property array<string, bool|float|string|null> $values
 * @property string|null $subject_type
 * @property int|null $subject_id
 * @property int $submitted_by_user_id
 * @property Carbon $submitted_at
 */
class FormSubmission extends Model {
    use Auditable;

    use BelongsToOrganization;
    /** @use HasFactory<FormSubmissionFactory> */
    use HasFactory;
    use HasSqid;

    use SoftDeletes;

    protected $fillable = [
        'organization_id',
        'form_template_id',
        'fields_snapshot',
        'values',
        'subject_type',
        'subject_id',
        'submitted_by_user_id',
        'submitted_at',
    ];

    protected $casts = [
        'fields_snapshot' => 'array',
        'values' => 'array',
        'submitted_at' => 'datetime',
    ];

    /** @return BelongsTo<FormTemplate, $this> */
    public function template(): BelongsTo {
        return $this->belongsTo(FormTemplate::class, 'form_template_id');
    }

    /** @return MorphTo<Model, $this> */
    public function subject(): MorphTo {
        return $this->morphTo();
    }

    /** @return BelongsTo<User, $this> */
    public function submitter(): BelongsTo {
        return $this->belongsTo(User::class, 'submitted_by_user_id');
    }
}
