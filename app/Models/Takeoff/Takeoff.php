<?php
/*
 * Created on   : Fri Oct 02 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : Takeoff.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Models\Takeoff;

use App\Enums\Takeoff\TakeoffStatus;
use App\Models\Concerns\{Auditable, BelongsToOrganization, HasAttachments, HasSqid};
use App\Models\Diary\DiaryEntry;
use App\Models\Gaeb\BillOfQuantity;
use App\Models\Platform\User;
use App\Models\Project\Project;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\{BelongsTo, HasMany};

/**
 * Aufmaßblatt (MVP-1058): Mengenermittlung zu einem Auftrag, Projekt oder LV.
 * Abgeschlossen ist es gesperrt; dann werden die Mengen übernommen.
 *
 * @property int $id
 * @property int $organization_id
 * @property int|null $diary_entry_id
 * @property int|null $project_id
 * @property int|null $bill_of_quantity_id
 * @property string $title
 * @property \Illuminate\Support\Carbon|null $measured_on
 * @property TakeoffStatus $status
 * @property string|null $note
 * @property int|null $created_by
 * @property int|null $updated_by
 * @property-read \Illuminate\Database\Eloquent\Collection<int, TakeoffLine> $lines
 * @property-read \Illuminate\Database\Eloquent\Collection<int, TakeoffTransfer> $transfers
 */
class Takeoff extends Model {
    use Auditable;
    use BelongsToOrganization;
    use HasAttachments;
    use HasSqid;

    protected $fillable = [
        'organization_id',
        'diary_entry_id',
        'project_id',
        'bill_of_quantity_id',
        'title',
        'measured_on',
        'status',
        'note',
        'created_by',
        'updated_by',
    ];

    /** @var array<string, mixed> */
    protected $attributes = ['status' => 'draft'];

    /** @var array<string, string> */
    protected $casts = [
        'measured_on' => 'date',
        'status' => TakeoffStatus::class,
    ];

    /** @return HasMany<TakeoffLine, $this> */
    public function lines(): HasMany {
        return $this->hasMany(TakeoffLine::class)->orderBy('position')->orderBy('id');
    }

    /** @return HasMany<TakeoffTransfer, $this> Übernahmen in Angebot, Rechnung oder Leistungsstand (MVP-1059) */
    public function transfers(): HasMany {
        return $this->hasMany(TakeoffTransfer::class);
    }

    /** @return BelongsTo<DiaryEntry, $this> */
    public function diaryEntry(): BelongsTo {
        return $this->belongsTo(DiaryEntry::class);
    }

    /** @return BelongsTo<Project, $this> */
    public function project(): BelongsTo {
        return $this->belongsTo(Project::class);
    }

    /** @return BelongsTo<BillOfQuantity, $this> */
    public function billOfQuantity(): BelongsTo {
        return $this->belongsTo(BillOfQuantity::class);
    }

    /** @return BelongsTo<User, $this> */
    public function creator(): BelongsTo {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function isEditable(): bool {
        return $this->status === TakeoffStatus::Draft;
    }

    /** Träger, an dem das Blatt hängt — Auftrag vor Projekt vor LV. */
    public function carrier(): ?Model {
        return $this->diaryEntry ?? $this->project ?? $this->billOfQuantity;
    }
}
