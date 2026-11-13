<?php
/*
 * Created on   : Thu Nov 12 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : KnowledgePage.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Models\Knowledge;

use App\Enums\Knowledge\{KnowledgePageKind, KnowledgePageStatus};
use App\Models\Concerns\{Auditable, BelongsToOrganization, HasAttachments, HasSqid, HasTags};
use App\Models\Platform\User;
use Database\Factories\Knowledge\KnowledgePageFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\{Model, SoftDeletes};
use Illuminate\Database\Eloquent\Relations\{BelongsTo, HasMany};
use Illuminate\Support\Carbon;

/**
 * Wissensseite (Feature 191, MVP-1664–1668): Markdown-Text bzw. Anleitung aus
 * Bildschirmfotos, mit Fassungen, Freigabe, Verantwortung, Prüfintervall,
 * Gültigkeit, Pflichtlektüre und Papierkorb (SoftDeletes, endgültig erst nach
 * Frist oder mit Recht).
 *
 * `version` ist die Nummer der aktuellen Fassung, `approved_version` die der
 * zuletzt freigegebenen — nur diese sehen Leserinnen und Leser ohne
 * Bearbeitungsrecht, und nur sie lässt sich als gelesen bestätigen. Geändert
 * wird ausschließlich über {@see \App\Services\Knowledge\Pages\KnowledgePageService}.
 *
 * @property int $id
 * @property int $organization_id
 * @property int $knowledge_space_id
 * @property int|null $knowledge_section_id
 * @property int|null $parent_id
 * @property KnowledgePageKind $kind
 * @property int $position
 * @property string $title
 * @property string|null $body
 * @property KnowledgePageStatus $status
 * @property int $version
 * @property int|null $approved_version
 * @property Carbon|null $approved_at
 * @property int|null $approved_by
 * @property int|null $owner_user_id
 * @property int|null $review_interval_months
 * @property Carbon|null $next_review_on
 * @property Carbon|null $reviewed_at
 * @property int|null $reviewed_by
 * @property Carbon|null $review_warned_at
 * @property Carbon|null $valid_until
 * @property Carbon|null $valid_warned_at
 * @property bool $is_emergency
 * @property int|null $created_by
 * @property int|null $updated_by
 * @property int|null $deleted_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 * @property-read KnowledgeSpace|null $space
 * @property-read KnowledgeSection|null $section
 * @property-read User|null $owner
 * @property-read User|null $approver
 * @property-read User|null $reviewer
 * @property-read User|null $creator
 * @property-read User|null $updater
 * @property-read User|null $trasher
 * @property-read KnowledgePage|null $parent
 */
class KnowledgePage extends Model {
    use Auditable {
        auditRedact as private auditableRedact;
    }
    use BelongsToOrganization;
    use HasAttachments;
    /** @use HasFactory<KnowledgePageFactory> */
    use HasFactory;
    use HasSqid;
    use HasTags;
    use SoftDeletes;

    /** Bilder der Anleitungsschritte hängen als Anhänge an der Seite, gekennzeichnet über `meta_type`. */
    public const string META_GUIDE_STEP = 'knowledge_guide_step';

    /** Unterseiten höchstens bis zu dieser Tiefe (Seite = 1). */
    public const int MAX_DEPTH = 3;

    public const int MAX_BODY_LENGTH = 100000;

    /** Tage im Papierkorb, bevor `knowledge-pages:empty-trash` endgültig löscht. */
    public const int TRASH_DAYS = 30;

    protected $fillable = [
        'organization_id',
        'knowledge_space_id',
        'knowledge_section_id',
        'parent_id',
        'kind',
        'position',
        'title',
        'body',
        'status',
        'version',
        'approved_version',
        'approved_at',
        'approved_by',
        'owner_user_id',
        'review_interval_months',
        'next_review_on',
        'reviewed_at',
        'reviewed_by',
        'review_warned_at',
        'valid_until',
        'valid_warned_at',
        'is_emergency',
        'created_by',
        'updated_by',
        'deleted_by',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'kind' => KnowledgePageKind::class,
        'status' => KnowledgePageStatus::class,
        'position' => 'integer',
        'version' => 'integer',
        'approved_version' => 'integer',
        'approved_at' => 'datetime',
        'review_interval_months' => 'integer',
        'next_review_on' => 'date',
        'reviewed_at' => 'datetime',
        'review_warned_at' => 'datetime',
        'valid_until' => 'date',
        'valid_warned_at' => 'datetime',
        'is_emergency' => 'boolean',
    ];

    /**
     * Der Seitentext gehört in die Fassungen, nicht ins Audit-Protokoll: die
     * Änderung wird vermerkt, der Text nicht.
     *
     * @return array<int, string>
     */
    protected function auditRedact(): array {
        return [...$this->auditableRedact(), 'body'];
    }

    /** @return BelongsTo<KnowledgeSpace, $this> */
    public function space(): BelongsTo {
        return $this->belongsTo(KnowledgeSpace::class, 'knowledge_space_id');
    }

    /** @return BelongsTo<KnowledgeSection, $this> */
    public function section(): BelongsTo {
        return $this->belongsTo(KnowledgeSection::class, 'knowledge_section_id');
    }

    /** @return BelongsTo<self, $this> */
    public function parent(): BelongsTo {
        return $this->belongsTo(self::class, 'parent_id');
    }

    /** @return HasMany<self, $this> */
    public function children(): HasMany {
        return $this->hasMany(self::class, 'parent_id');
    }

    /** @return HasMany<KnowledgePageVersion, $this> */
    public function versions(): HasMany {
        return $this->hasMany(KnowledgePageVersion::class)->orderByDesc('version');
    }

    /** @return HasMany<KnowledgeGuideStep, $this> */
    public function steps(): HasMany {
        return $this->hasMany(KnowledgeGuideStep::class)->orderBy('position')->orderBy('id');
    }

    /** @return HasMany<KnowledgePageAssignment, $this> */
    public function assignments(): HasMany {
        return $this->hasMany(KnowledgePageAssignment::class);
    }

    /** @return HasMany<KnowledgePageRead, $this> */
    public function reads(): HasMany {
        return $this->hasMany(KnowledgePageRead::class);
    }

    /** @return BelongsTo<User, $this> */
    public function owner(): BelongsTo {
        return $this->belongsTo(User::class, 'owner_user_id');
    }

    /** @return BelongsTo<User, $this> */
    public function approver(): BelongsTo {
        return $this->belongsTo(User::class, 'approved_by');
    }

    /** @return BelongsTo<User, $this> */
    public function reviewer(): BelongsTo {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    /** @return BelongsTo<User, $this> */
    public function creator(): BelongsTo {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** @return BelongsTo<User, $this> */
    public function updater(): BelongsTo {
        return $this->belongsTo(User::class, 'updated_by');
    }

    /** @return BelongsTo<User, $this> */
    public function trasher(): BelongsTo {
        return $this->belongsTo(User::class, 'deleted_by');
    }

    public function isGuide(): bool {
        return $this->kind === KnowledgePageKind::Guide;
    }

    /** Wurde je eine Fassung freigegeben? Sonst sehen nur Bearbeitende die Seite. */
    public function isPublished(): bool {
        return $this->approved_version !== null;
    }

    /** Wartet die aktuelle Fassung noch auf ihre Freigabe? */
    public function hasPendingVersion(): bool {
        return $this->approved_version !== $this->version;
    }
}
