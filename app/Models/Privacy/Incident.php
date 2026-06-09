<?php
/*
 * Created on   : Tue Jun 09 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : Incident.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Models\Privacy;

use App\Enums\Privacy\{IncidentStatus, IncidentType};
use App\Models\Concerns\{BelongsToOrganization, HasSqid};
use App\Models\Privacy\Casts\RecordEncrypted;
use App\Models\Privacy\Concerns\ProvidesRecordDek;
use App\Models\User;
use App\Services\Privacy\DataProtectionCryptoService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\{BelongsTo, HasMany};

/**
 * Datenschutzvorfall (Art. 33/34). Sachverhalt/Betroffenes/Massnahmen sind
 * per-Fall verschluesselt (DEK in `dek_wrapped`); der pruefbare Verlauf liegt in
 * der {@see IncidentEvent}-Hash-Kette. Die 72-h-Meldefrist beginnt mit der
 * Entdeckung.
 *
 * @property string|null $summary_ciphertext   Klartext beim Lesen/Setzen
 * @property string|null $affected_ciphertext
 * @property string|null $measures_ciphertext
 * @property string|null $lessons_ciphertext
 */
class Incident extends Model implements ProvidesRecordDek {
    use BelongsToOrganization;
    use HasSqid;

    protected $table = 'privacy_incidents';

    protected $fillable = [
        'organization_id',
        'incident_number',
        'type',
        'status',
        'occurred_at',
        'discovered_at',
        'reported_internally_at',
        'authority_deadline_at',
        'risk_level',
        'affected_count',
        'notify_authority',
        'notify_subjects',
        'authority_notified_at',
        'subjects_notified_at',
        'assigned_user_id',
        'summary_ciphertext',
        'affected_ciphertext',
        'measures_ciphertext',
        'lessons_ciphertext',
        'dek_wrapped',
        'closed_at',
        'created_by',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'type' => IncidentType::class,
        'status' => IncidentStatus::class,
        'summary_ciphertext' => RecordEncrypted::class,
        'affected_ciphertext' => RecordEncrypted::class,
        'measures_ciphertext' => RecordEncrypted::class,
        'lessons_ciphertext' => RecordEncrypted::class,
        'occurred_at' => 'datetime',
        'discovered_at' => 'datetime',
        'reported_internally_at' => 'datetime',
        'authority_deadline_at' => 'datetime',
        'authority_notified_at' => 'datetime',
        'subjects_notified_at' => 'datetime',
        'closed_at' => 'datetime',
        'notify_authority' => 'boolean',
        'notify_subjects' => 'boolean',
    ];

    private ?string $plainDek = null;

    public function initializeDek(): void {
        $crypto = app(DataProtectionCryptoService::class);
        $this->plainDek = $crypto->generateDek();
        $this->setAttribute('dek_wrapped', $crypto->wrapDek($this->plainDek));
    }

    public function recordDek(): ?string {
        if ($this->plainDek !== null) {
            return $this->plainDek;
        }
        $wrapped = $this->getAttribute('dek_wrapped');
        if (empty($wrapped)) {
            return null;
        }

        return $this->plainDek = app(DataProtectionCryptoService::class)->unwrapDek((string) $wrapped);
    }

    public function shredDek(): void {
        $this->plainDek = null;
        $this->forceFill(['dek_wrapped' => null])->save();
    }

    /** @return BelongsTo<User, $this> */
    public function assignedUser(): BelongsTo {
        return $this->belongsTo(User::class, 'assigned_user_id');
    }

    /** @return HasMany<IncidentEvent, $this> */
    public function events(): HasMany {
        return $this->hasMany(IncidentEvent::class, 'incident_id')->orderBy('id');
    }

    /** @return HasMany<Measure, $this> */
    public function measures(): HasMany {
        return $this->hasMany(Measure::class, 'incident_id');
    }

    /** 72-h-Frist verstrichen und noch nicht gemeldet? */
    public function isDeadlineBreached(): bool {
        $deadline = $this->getAttribute('authority_deadline_at');

        return $deadline !== null
            && $deadline->isPast()
            && $this->getAttribute('authority_notified_at') === null
            && $this->status->isOpen();
    }
}
