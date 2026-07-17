<?php
/*
 * Created on   : Thu Jul 16 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : AiTextSuggestion.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Models\Ai;

use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Factories\{Factory, HasFactory};
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Carbon;

/**
 * KI-Textvorschlag (Feature 084, MVP-402): Vorschlag zu einer Rechnungs-
 * oder Angebotsposition. KI schreibt nie still — erst die Übernahme
 * (accept) ändert Fachdaten; offene Vorschläge verfallen bei
 * Ausstellung/Versand (expired). Kein Auditable-Trait: Erzeugung wird
 * als `ai.invoked` auditiert, die Entscheidung als
 * `ai.suggestion_decided` (ohne Klartext) im Service.
 *
 * @property int $id
 * @property int|null $organization_id
 * @property string $subject_type
 * @property int $subject_id
 * @property string $capability
 * @property string|null $original
 * @property string $suggestion
 * @property string $status
 * @property int|null $connection_id
 * @property string|null $provider
 * @property bool $fallback_used
 * @property bool $from_cache
 * @property Carbon|null $decided_at
 */
class AiTextSuggestion extends Model {
    use BelongsToOrganization;

    /** @use HasFactory<Factory<static>> */
    use HasFactory;

    public const STATUS_PROPOSED = 'proposed';

    public const STATUS_ACCEPTED = 'accepted';

    public const STATUS_EDITED = 'edited';

    public const STATUS_REJECTED = 'rejected';

    public const STATUS_EXPIRED = 'expired';

    protected $fillable = [
        'organization_id',
        'subject_type',
        'subject_id',
        'capability',
        'original',
        'suggestion',
        'status',
        'connection_id',
        'provider',
        'fallback_used',
        'from_cache',
        'created_by_user_id',
        'decided_by_user_id',
        'decided_at',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'fallback_used' => 'boolean',
        'from_cache' => 'boolean',
        'decided_at' => 'datetime',
    ];

    /** @return MorphTo<Model, $this> */
    public function subject(): MorphTo {
        return $this->morphTo();
    }

    public function isOpen(): bool {
        return $this->status === self::STATUS_PROPOSED;
    }
}
