<?php
/*
 * Created on   : Thu May 21 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : EventParticipant.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Models;

use App\Enums\Event\ParticipantRole;
use App\Enums\Event\ParticipantStatus;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\Pivot;
use Illuminate\Support\Carbon;

/**
 * Pivot-Modell für event_user — bietet Convenience-Methoden
 * (markAttended, markDeclined, …) und sauberes Enum-Casting.
 *
 * @property int $event_id
 * @property int $user_id
 * @property ParticipantRole $role
 * @property ParticipantStatus $status
 * @property Carbon|null $responded_at
 * @property Carbon|null $attended_at
 * @property Carbon|null $certificate_issued_at
 * @property Carbon|null $certificate_expires_at
 * @property string|null $notes
 */
class EventParticipant extends Pivot {
    protected $table = 'event_user';

    public $incrementing = true;

    public $timestamps = true;

    /** @var array<string, string> */
    protected $casts = [
        'role' => ParticipantRole::class,
        'status' => ParticipantStatus::class,
        'responded_at' => 'datetime',
        'attended_at' => 'datetime',
        'certificate_issued_at' => 'date',
        'certificate_expires_at' => 'date',
    ];

    public function markAttended(?Carbon $at = null): void {
        $this->forceFill([
            'status' => ParticipantStatus::Attended,
            'attended_at' => $at ?? now(),
        ])->save();
    }

    public function markNoShow(): void {
        $this->forceFill([
            'status' => ParticipantStatus::NoShow,
        ])->save();
    }

    public function accept(): void {
        $this->forceFill([
            'status' => ParticipantStatus::Accepted,
            'responded_at' => now(),
        ])->save();
    }

    public function decline(): void {
        $this->forceFill([
            'status' => ParticipantStatus::Declined,
            'responded_at' => now(),
        ])->save();
    }

    /** @return BelongsTo<Event, $this> */
    public function event(): BelongsTo {
        return $this->belongsTo(Event::class);
    }

    public function hasValidCertificate(?Carbon $on = null): bool {
        if ($this->certificate_issued_at === null) {
            return false;
        }
        if ($this->certificate_expires_at === null) {
            return true;
        }

        return ($on ?? now())->lessThanOrEqualTo($this->certificate_expires_at);
    }
}
