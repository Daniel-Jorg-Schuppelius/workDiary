<?php
/*
 * Created on   : Wed Jun 10 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : FormSubmission.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Models\Form;

use App\Enums\Form\FormSubmissionStatus;
use App\Models\Attachments\Attachment;
use App\Models\Concerns\{Auditable, BelongsToOrganization, HasAttachments, HasSqid};
use App\Models\Contracts\AutosavesFieldDocument;
use App\Models\Platform\User;
use App\Services\Fields\{FieldSchema, FieldValues};
use Database\Factories\Form\FormSubmissionFactory;
use Illuminate\Database\Eloquent\Builder;
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
 * Lange Formulare (Feature 203, MVP-1650): Eine Einsendung beginnt als
 * Entwurf, der je Feld gespeichert wird ({@see AutosavesFieldDocument},
 * Version gegen stilles Überschreiben), und wird mit „Absenden“ zum
 * Nachweis. Entwürfe sieht und bearbeitet nur, wer sie angelegt hat.
 *
 * @property int $id
 * @property int $organization_id
 * @property int $form_template_id
 * @property list<array{key: string, label: string, type: string, required: bool, options: list<string>, help: string|null, unit: string|null}> $fields_snapshot
 * @property array<string, mixed> $values
 * @property FormSubmissionStatus $status
 * @property int $version
 * @property string|null $subject_type
 * @property int|null $subject_id
 * @property int $submitted_by_user_id
 * @property Carbon|null $submitted_at
 * @property Carbon|null $updated_at
 */
class FormSubmission extends Model implements AutosavesFieldDocument {
    use Auditable;

    use BelongsToOrganization;
    // Foto-/Datei-/Unterschrift-Felder (Rang 32) legen ihren Inhalt als
    // Attachment ab (meta_type `field:<key>`).
    use HasAttachments;

    /** @use HasFactory<FormSubmissionFactory> */
    use HasFactory;

    use HasSqid;

    use SoftDeletes;

    protected $fillable = [
        'organization_id',
        'form_template_id',
        'fields_snapshot',
        'values',
        'status',
        'version',
        'subject_type',
        'subject_id',
        'submitted_by_user_id',
        'submitted_at',
    ];

    protected $casts = [
        'fields_snapshot' => 'array',
        'values' => 'array',
        'status' => FormSubmissionStatus::class,
        'version' => 'integer',
        'submitted_at' => 'datetime',
    ];

    /** Bestandswege (Absenden ohne Entwurf, Offline-Sync, Demo) legen abgesendete Einsendungen an. */
    protected $attributes = [
        'status' => 'submitted',
        'version' => 1,
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

    public function isDraft(): bool {
        return $this->status === FormSubmissionStatus::Draft;
    }

    /**
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeSubmitted(Builder $query): Builder {
        return $query->where('status', FormSubmissionStatus::Submitted->value);
    }

    /**
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeDrafts(Builder $query): Builder {
        return $query->where('status', FormSubmissionStatus::Draft->value);
    }

    // ── AutosavesFieldDocument (Feature 203, MVP-1650) ────────────────

    public function fieldDocumentSchema(): FieldSchema {
        return FieldSchema::fromArray($this->fields_snapshot);
    }

    public function fieldDocumentValues(): FieldValues {
        return FieldValues::fromArray($this->values);
    }

    public function fieldDocumentVersion(): int {
        return (int) $this->version;
    }

    public function acceptsFieldAutosave(): bool {
        return $this->isDraft();
    }

    /**
     * Zwischenstand still schreiben: Ein Entwurf ist noch kein Nachweis —
     * protokolliert wird das Absenden (Werte vorher/nachher), nicht jedes Feld.
     */
    public function storeFieldAutosave(FieldValues $values, int $version): void {
        $this->forceFill(['values' => $values->toArray(), 'version' => $version])->saveQuietly();
    }
}
