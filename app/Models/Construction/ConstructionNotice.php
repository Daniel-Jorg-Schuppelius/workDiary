<?php
/*
 * Created on   : Wed Aug 26 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ConstructionNotice.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Models\Construction;

use App\Enums\Construction\ConstructionNoticeStatus;
use App\Enums\DocumentDesign\RenderDocumentKind;
use App\Models\Concerns\{Auditable, BelongsToOrganization, HasAttachments, HasSqid};
use App\Models\{Customer, DiaryEntry, DocumentDispatch, Project, Site, User, WeatherSnapshot};
use Illuminate\Database\Eloquent\{Builder, Model};
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Foermliches VOB/B-Schreiben (Feature 062, MVP-728, H23): Behinderungsanzeige
 * (§ 6 VOB/B) oder Bedenkenanmeldung (§ 4 Abs. 3 VOB/B) — die Belegart steht in
 * `kind` ({@see RenderDocumentKind}).
 *
 * Anlass ist in der Regel ein Tagebucheintrag; die Wetterlage des Anlasstags
 * haengt als unveraenderlicher {@see WeatherSnapshot} daran. Der Zugangsnachweis
 * liegt NICHT hier, sondern im generischen Belegversand
 * ({@see DocumentDispatch}) — `sent_at` ist nur dessen Projektion.
 *
 * `claims_time_extension` ist ein reiner **Vermerk**: WorkDiary verschiebt
 * daraufhin keine Frist. Ob sich die Bauzeit verlaengert, entscheiden die
 * Vertragsparteien.
 *
 * @property int $id
 * @property int $organization_id
 * @property int $notice_no
 * @property RenderDocumentKind $kind
 * @property ConstructionNoticeStatus $status
 * @property Carbon $occurred_on
 * @property Carbon|null $sent_at
 * @property Carbon|null $acknowledged_at
 * @property bool $claims_time_extension
 */
class ConstructionNotice extends Model {
    use Auditable;
    use BelongsToOrganization;
    use HasAttachments;
    use HasSqid;

    protected $table = 'construction_notices';

    protected $fillable = [
        'organization_id',
        'notice_no',
        'kind',
        'status',
        'diary_entry_id',
        'project_id',
        'site_id',
        'customer_id',
        'weather_snapshot_id',
        'recipient_name',
        'recipient_email',
        'subject',
        'occurred_on',
        'facts',
        'impact_schedule',
        'impact_cost',
        'claims_time_extension',
        'legal_reference',
        'sent_at',
        'acknowledged_at',
        'acknowledged_note',
        'created_by',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'kind' => RenderDocumentKind::class,
        'status' => ConstructionNoticeStatus::class,
        'occurred_on' => 'date',
        'sent_at' => 'datetime',
        'acknowledged_at' => 'datetime',
        'claims_time_extension' => 'boolean',
    ];

    /** Die beiden Belegarten, die als VOB/B-Schreiben zulaessig sind. */
    public const KINDS = [
        RenderDocumentKind::ConstructionObstructionNotice,
        RenderDocumentKind::ConstructionConcernNotice,
    ];

    /** Vorbelegter Rechtsverweis je Belegart — reiner Text, keine Rechtsberatung. */
    public static function defaultLegalReference(RenderDocumentKind $kind): string {
        return $kind === RenderDocumentKind::ConstructionConcernNotice
            ? (string) __('construction.legal.concern')
            : (string) __('construction.legal.obstruction');
    }

    public function displayNo(): string {
        $prefix = $this->kind === RenderDocumentKind::ConstructionConcernNotice ? 'BE' : 'BA';

        return $prefix . '-' . $this->notice_no;
    }

    public function isEditable(): bool {
        return $this->status->isEditable();
    }

    /** @return BelongsTo<DiaryEntry, $this> */
    public function diaryEntry(): BelongsTo {
        return $this->belongsTo(DiaryEntry::class, 'diary_entry_id');
    }

    /** @return BelongsTo<Project, $this> */
    public function project(): BelongsTo {
        return $this->belongsTo(Project::class, 'project_id');
    }

    /** @return BelongsTo<Site, $this> */
    public function site(): BelongsTo {
        return $this->belongsTo(Site::class, 'site_id');
    }

    /** @return BelongsTo<Customer, $this> */
    public function customer(): BelongsTo {
        return $this->belongsTo(Customer::class, 'customer_id');
    }

    /** @return BelongsTo<WeatherSnapshot, $this> */
    public function weatherSnapshot(): BelongsTo {
        return $this->belongsTo(WeatherSnapshot::class, 'weather_snapshot_id');
    }

    /** @return BelongsTo<User, $this> */
    public function creator(): BelongsTo {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Zustellnachweise aus dem generischen Belegversand — bewusst als Query
     * statt als Relation: `document_dispatches` traegt keinen FK auf den Beleg
     * (das Nachweis-Log toleriert verwaiste Zeilen, Feature 128).
     *
     * @return Builder<DocumentDispatch>
     */
    public function dispatches(): Builder {
        return DocumentDispatch::query()
            ->forDocument($this->kind, (int) $this->getKey())
            ->orderByDesc('created_at');
    }
}
