<?php
/*
 * Created on   : Wed Jul 08 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : DpiaStep.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Models\Privacy;

use App\Models\Concerns\BelongsToOrganization;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Ein Schritt des geführten DSFA-Workflows (Nachtrag 043a):
 * Beschreibung → Notwendigkeit → Risiken → Maßnahmen → Freigabe.
 * Die inhaltlichen Schritte schreiben ihr Ergebnis in die zugehörigen
 * Felder der {@see Dpia}; der Freigabe-Schritt setzt das Ergebnis.
 *
 * @property int $id
 * @property int $organization_id
 * @property int $dpia_id
 * @property string $step
 * @property int $position
 * @property string $status
 * @property string|null $content
 * @property int|null $completed_by
 * @property \Illuminate\Support\Carbon|null $completed_at
 */
class DpiaStep extends Model {
    use BelongsToOrganization;

    /** Feste Schrittfolge des Workflows (Position = Index). */
    public const STEPS = ['description', 'necessity', 'risks', 'mitigations', 'approval'];

    public const STATUS_PENDING = 'pending';

    public const STATUS_DONE = 'done';

    protected $table = 'privacy_dpia_steps';

    protected $fillable = [
        'organization_id',
        'dpia_id',
        'step',
        'position',
        'status',
        'content',
        'completed_by',
        'completed_at',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'completed_at' => 'datetime',
    ];

    /** @return BelongsTo<Dpia, $this> */
    public function dpia(): BelongsTo {
        return $this->belongsTo(Dpia::class, 'dpia_id');
    }

    /** @return BelongsTo<User, $this> */
    public function completedBy(): BelongsTo {
        return $this->belongsTo(User::class, 'completed_by');
    }

    public function isDone(): bool {
        return $this->status === self::STATUS_DONE;
    }

    /** Anzeige-Label je Schritt (Rohdeutsch = JSON-Key-Konvention). */
    public function label(): string {
        return match ($this->step) {
            'description' => __('Beschreibung der Verarbeitung'),
            'necessity' => __('Notwendigkeit und Verhältnismäßigkeit'),
            'risks' => __('Risiken für Betroffene'),
            'mitigations' => __('Abhilfemaßnahmen'),
            'approval' => __('Freigabe'),
            default => $this->step,
        };
    }
}
