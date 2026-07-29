<?php
/*
 * Created on   : Mon Jun 29 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : IntegrationInboxItem.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\{BelongsToOrganization, HasSqid};
use Illuminate\Database\Eloquent\Factories\{Factory, HasFactory};
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Carbon;

/**
 * Ein Eintrag der universellen Zuordnungs-Inbox (Datenimport-Drehscheibe).
 *
 * @property int $id
 * @property int|null $organization_id
 * @property string $plugin_id
 * @property string|null $source
 * @property string $target_type
 * @property string $external_type
 * @property string|null $external_id
 * @property string $dedupe_key
 * @property string|null $group_key
 * @property string $case_type
 * @property string $status
 * @property string|null $referenceable_type
 * @property int|null $referenceable_id
 * @property array<int, array{id: int, score?: float, reasons?: list<string>}>|null $candidate_ids
 * @property string|null $resolved_to_type
 * @property int|null $resolved_to_id
 * @property array<string, mixed> $remote_snapshot
 * @property array<string, mixed>|null $mapped_snapshot
 * @property array<string, mixed>|null $local_snapshot
 * @property array<int, string>|null $diff_fields
 * @property string|null $display_title
 * @property string|null $display_subtitle
 * @property Carbon|null $occurred_at
 * @property int|null $resolved_by
 * @property Carbon|null $resolved_at
 */
class IntegrationInboxItem extends Model {
    use BelongsToOrganization;

    /** @use HasFactory<Factory<static>> */
    use HasFactory;
    use HasSqid;

    // Fall-Typen
    public const CASE_UNMATCHED = 'unmatched';
    /**
     * Marker im `remote_snapshot`: dieser Fall lässt sich nur zur Kenntnis
     * nehmen. Der lokale Datensatz ist festgeschrieben (abgerechnet/exportiert),
     * ein „Fremdstand übernehmen" wäre eine nachträgliche Belegänderung.
     */
    public const RESOLUTION_ACKNOWLEDGE_ONLY = 'acknowledge_only';

    public const CASE_CONFLICT = 'conflict';
    public const CASE_AMBIGUOUS = 'ambiguous';

    // Status
    public const STATUS_OPEN = 'open';
    public const STATUS_RESOLVED_LINKED = 'resolved_linked';
    public const STATUS_RESOLVED_CREATED = 'resolved_created';
    public const STATUS_RESOLVED_LOCAL = 'resolved_local';
    public const STATUS_RESOLVED_REMOTE = 'resolved_remote';
    public const STATUS_DISMISSED = 'dismissed';

    // Quelle des Imports (für csv-import)
    public const PLUGIN_CSV = 'csv-import';

    protected $fillable = [
        'organization_id',
        'plugin_id',
        'source',
        'target_type',
        'external_type',
        'external_id',
        'dedupe_key',
        'group_key',
        'case_type',
        'status',
        'referenceable_type',
        'referenceable_id',
        'candidate_ids',
        'resolved_to_type',
        'resolved_to_id',
        'remote_snapshot',
        'mapped_snapshot',
        'local_snapshot',
        'diff_fields',
        'display_title',
        'display_subtitle',
        'occurred_at',
        'resolved_by',
        'resolved_at',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'candidate_ids' => 'array',
        'remote_snapshot' => 'array',
        'mapped_snapshot' => 'array',
        'local_snapshot' => 'array',
        'diff_fields' => 'array',
        'occurred_at' => 'datetime',
        'resolved_at' => 'datetime',
    ];

    public function isOpen(): bool {
        return $this->status === self::STATUS_OPEN;
    }

    /**
     * Der lokale (Haupt-)Kandidat bei conflict/ambiguous.
     *
     * @return MorphTo<Model, $this>
     */
    public function referenceable(): MorphTo {
        return $this->morphTo();
    }

    /**
     * Der lokale Datensatz, auf den der Eintrag aufgelöst wurde.
     *
     * @return MorphTo<Model, $this>
     */
    public function resolvedTo(): MorphTo {
        return $this->morphTo(__FUNCTION__, 'resolved_to_type', 'resolved_to_id');
    }
}
