<?php
/*
 * Created on   : Fri Aug 28 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : LearningUnit.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Models\Learning;

use App\Enums\Learning\{LearningProgressStatus, LearningUnitKind};
use App\Models\Concerns\{Auditable, BelongsToOrganization, HasAttachments, HasSqid};
use App\Models\Event;
use Illuminate\Database\Eloquent\Factories\{Factory, HasFactory};
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\{BelongsTo, HasOne};
use Illuminate\Support\Carbon;

/**
 * Lerneinheit (Feature 149): die kleinste abschließbare Einheit. `content`
 * trägt die Inhaltsblöcke bzw. den Zeiger auf die Fremdressource,
 * `completion_rule` das Abschlusskriterium und `release_rule` den
 * Freischaltplan.
 *
 * @property int $id
 * @property int $organization_id
 * @property int $learning_course_id
 * @property int|null $learning_section_id
 * @property int|null $event_id
 * @property int|null $registration_lead_hours
 * @property int|null $cancellation_lead_hours
 * @property string $title
 * @property LearningUnitKind $kind
 * @property int $position
 * @property bool $is_mandatory
 * @property bool $is_preview
 * @property int $points
 * @property int|null $duration_minutes
 * @property string|null $content
 * @property array<string, mixed>|null $completion_rule
 * @property array<string, mixed>|null $release_rule
 */
class LearningUnit extends Model {
    use Auditable;

    use BelongsToOrganization;

    use HasAttachments;
    /** @use HasFactory<Factory<static>> */
    use HasFactory;

    use HasSqid;

    protected $fillable = [
        'organization_id',
        'learning_course_id',
        'learning_section_id',
        'event_id',
        'title',
        'kind',
        'registration_lead_hours',
        'cancellation_lead_hours',
        'position',
        'is_mandatory',
        'points',
        'duration_minutes',
        'content',
        'completion_rule',
        'release_rule',
        'is_preview',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'kind' => LearningUnitKind::class,
        'position' => 'integer',
        'is_mandatory' => 'boolean',
        'is_preview' => 'boolean',
        'points' => 'integer',
        'duration_minutes' => 'integer',
        'registration_lead_hours' => 'integer',
        'cancellation_lead_hours' => 'integer',
        'completion_rule' => 'array',
        'release_rule' => 'array',
    ];

    /** @return BelongsTo<LearningCourse, $this> */
    public function course(): BelongsTo {
        return $this->belongsTo(LearningCourse::class, 'learning_course_id');
    }

    /** @return BelongsTo<LearningSection, $this> */
    public function section(): BelongsTo {
        return $this->belongsTo(LearningSection::class, 'learning_section_id');
    }

    /** Prüfung dieser Einheit (nur bei kind = quiz). */
    /** @return HasOne<LearningQuiz, $this> */
    public function quiz(): HasOne {
        return $this->hasOne(LearningQuiz::class, 'learning_unit_id');
    }

    /** Präsenztermin dieser Einheit (nur bei kind = event). */
    /** @return BelongsTo<Event, $this> */
    public function event(): BelongsTo {
        return $this->belongsTo(Event::class);
    }

    /** Aufgabe dieser Einheit (nur bei kind = assignment). */
    /** @return HasOne<LearningAssignment, $this> */
    public function assignment(): HasOne {
        return $this->hasOne(LearningAssignment::class, 'learning_unit_id');
    }

    /** @return HasOne<LearningScormPackage, $this> */
    public function scormPackage(): HasOne {
        return $this->hasOne(LearningScormPackage::class, 'learning_unit_id');
    }

    /** @return HasOne<LearningCmi5Package, $this> */
    public function cmi5Package(): HasOne {
        return $this->hasOne(LearningCmi5Package::class, 'learning_unit_id');
    }

    /** @return HasOne<LearningLtiLink, $this> */
    public function ltiLink(): HasOne {
        return $this->hasOne(LearningLtiLink::class, 'learning_unit_id');
    }

    /**
     * Meldet die Einheit ihr Ergebnis selbst (Termin, Abgabe, Prüfung, Kurspaket)?
     * Dann gibt es kein „erledigt" per Hand — es würde genau dieses Ergebnis überspringen.
     */
    public function reportsOwnResult(): bool {
        return match ($this->kind) {
            LearningUnitKind::Event => $this->event_id !== null,
            LearningUnitKind::Assignment => $this->assignment()->exists(),
            LearningUnitKind::Quiz => $this->quiz()->exists(),
            LearningUnitKind::Scorm => $this->scormPackage()->exists(),
            LearningUnitKind::Cmi5 => $this->cmi5Package()->exists(),
            default => false,
        };
    }

    /**
     * Freischaltplan (MVP-788): Tag, ab dem die Einheit für diese Einschreibung
     * offen ist — `after_days` ab Einschreibung und/oder festes Datum `at`;
     * es gilt das spätere. NULL = sofort.
     */
    public function releaseDateFor(LearningEnrollment $enrollment): ?Carbon {
        $rule = $this->release_rule ?? [];
        $latest = null;

        $afterDays = (int) ($rule['after_days'] ?? 0);
        if ($afterDays > 0) {
            $latest = Carbon::parse((string) ($enrollment->created_at ?? Carbon::now()))->startOfDay()->addDays($afterDays);
        }

        $at = $rule['at'] ?? null;
        if (is_string($at) && $at !== '') {
            $fixed = Carbon::parse($at)->startOfDay();
            $latest = $latest === null || $fixed->gt($latest) ? $fixed : $latest;
        }

        return $latest;
    }

    /**
     * Die Sperre selbst — an jeder Abschlussstelle geprüft, nicht nur in der
     * Ansicht (Player, Externe, Portal, Offline-Sync, Prüfung, Abgabe).
     *
     * @param  list<int>|null  $completedUnitIds  bereits bekannte abgeschlossene Einheiten (spart eine Abfrage)
     */
    public function isReleasedFor(LearningEnrollment $enrollment, ?Carbon $now = null, ?array $completedUnitIds = null): bool {
        $date = $this->releaseDateFor($enrollment);
        if ($date !== null && $date->gt(($now ?? Carbon::now())->copy()->startOfDay())) {
            return false;
        }

        return ! $this->isBlockedBySequenceFor($enrollment, $completedUnitIds);
    }

    /**
     * Lineare Kurse (`sequential`, MVP-798 / Befund C3-13): Das Kennzeichen wurde
     * gespeichert und exportiert, aber nirgends durchgesetzt. Offen ist eine
     * Einheit erst, wenn ihr unmittelbarer Vorgänger (Reihenfolge `position`,
     * wie in den Ansichten) abgeschlossen ist — die Kette trägt sich selbst.
     *
     * Bewusst ohne Zwischenspeicher: Der Offline-Abgleich schließt mehrere
     * Einheiten in einem Request ab, ein Cache sperrte dort fälschlich. Ansichten
     * mit fertiger Liste reichen sie herein und sparen die Abfrage.
     *
     * @param  list<int>|null  $completedUnitIds
     */
    public function isBlockedBySequenceFor(LearningEnrollment $enrollment, ?array $completedUnitIds = null): bool {
        $course = $enrollment->course;
        if (! $course instanceof LearningCourse || ! $course->sequential) {
            return false;
        }

        $predecessorId = null;
        $found = false;
        foreach ($course->units as $unit) {
            if ((int) $unit->id === (int) $this->id) {
                $found = true;
                break;
            }
            $predecessorId = (int) $unit->id;
        }
        if (! $found || $predecessorId === null) {
            return false;
        }

        if ($completedUnitIds !== null) {
            return ! in_array($predecessorId, array_map('intval', $completedUnitIds), true);
        }

        return ! $enrollment->progress()
            ->where('learning_unit_id', $predecessorId)
            ->where('status', LearningProgressStatus::Completed)
            ->exists();
    }

    /** Mindestverweildauer in Sekunden (`completion_rule.min_seconds`), 0 = keine. */
    public function minSeconds(): int {
        return max(0, (int) (($this->completion_rule ?? [])['min_seconds'] ?? 0));
    }

    /**
     * Inhaltsblöcke der Einheit.
     *
     * @return list<array<string, mixed>>
     */
    public function blocks(): array {
        if ($this->content === null || $this->content === '') {
            return [];
        }

        $decoded = json_decode($this->content, true);

        return is_array($decoded) ? array_values($decoded) : [];
    }
}
