<?php
/*
 * Created on   : Tue Aug 25 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : MedicalCheckup.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Models\Safety;

use App\Enums\Safety\MedicalCheckupKind;
use App\Models\Concerns\{Auditable, BelongsToOrganization, HasSqid};
use App\Models\User;
use Database\Factories\Safety\MedicalCheckupFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\{Model, SoftDeletes};
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Arbeitsmedizinische Vorsorge (ArbMedVV, Feature 132): Art (Pflicht/
 * Angebot/Wunsch), Anlass, Datum, nächste Fälligkeit und ob die
 * Vorsorgebescheinigung vorliegt.
 *
 * Datenminimierung (ArbMedVV § 6 Abs. 3, DSGVO Art. 9): Der Arbeitgeber
 * erhält nur die Bescheinigung über Teilnahme und Termin — dieses Register
 * speichert KEINE Befunde, Diagnosen oder Gesundheitsdaten; es gibt bewusst
 * kein Freitext-/Notizfeld über den Anlass hinaus.
 *
 * @property int $id
 * @property int $organization_id
 * @property int $user_id
 * @property MedicalCheckupKind $kind
 * @property string|null $occasion
 * @property Carbon $performed_on
 * @property Carbon|null $next_due_on
 * @property bool $certificate_on_file
 * @property int|null $created_by_user_id
 */
class MedicalCheckup extends Model {
    use Auditable;

    use BelongsToOrganization;
    /** @use HasFactory<MedicalCheckupFactory> */
    use HasFactory;
    use HasSqid;

    use SoftDeletes;

    protected $fillable = [
        'organization_id',
        'user_id',
        'kind',
        'occasion',
        'performed_on',
        'next_due_on',
        'certificate_on_file',
        'created_by_user_id',
    ];

    protected $casts = [
        'kind' => MedicalCheckupKind::class,
        'performed_on' => 'date',
        'next_due_on' => 'date',
        'certificate_on_file' => 'boolean',
    ];

    public function isDueOverdue(): bool {
        return $this->next_due_on !== null && $this->next_due_on->isPast() && ! $this->next_due_on->isToday();
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<User, $this> */
    public function createdBy(): BelongsTo {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    /**
     * Fällige/überfällige Vorsorgen (Termin erreicht/überschritten).
     *
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeDue(Builder $query): Builder {
        return $query->whereNotNull('next_due_on')
            ->whereDate('next_due_on', '<=', now()->toDateString());
    }
}
