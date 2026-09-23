<?php
/*
 * Created on   : Tue Sep 22 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ClubNotification.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Models\Club;

use App\Enums\Club\ClubNotificationStatus;
use App\Models\Calendar\Event;
use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Zustellprotokoll einer Vereinsnachricht (MVP-845): ein Eintrag je Anlass
 * und Empfänger. Geschrieben nur vom ClubMemberNotifier.
 *
 * @property int $id
 * @property int $organization_id
 * @property int $club_member_id
 * @property int|null $event_id
 * @property string $kind
 * @property string $dedupe_key
 * @property string $recipient
 * @property ClubNotificationStatus $status
 * @property string|null $error
 * @property array<string, mixed>|null $payload
 * @property Carbon|null $sent_at
 */
class ClubNotification extends Model {
    use BelongsToOrganization;

    public const KIND_REMINDER = 'reminder';

    public const KIND_RESCHEDULED = 'rescheduled';

    public const KIND_CANCELLED = 'cancelled';

    public const KIND_PROMOTED = 'promoted';

    protected $fillable = [
        'organization_id',
        'club_member_id',
        'event_id',
        'kind',
        'dedupe_key',
        'recipient',
        'status',
        'error',
        'payload',
        'sent_at',
    ];

    protected $casts = [
        'status' => ClubNotificationStatus::class,
        'payload' => 'array',
        'sent_at' => 'datetime',
    ];

    /** @return BelongsTo<ClubMember, $this> */
    public function member(): BelongsTo {
        return $this->belongsTo(ClubMember::class, 'club_member_id');
    }

    /** @return BelongsTo<Event, $this> */
    public function event(): BelongsTo {
        return $this->belongsTo(Event::class);
    }

    public function isFailed(): bool {
        return $this->status === ClubNotificationStatus::Failed;
    }

    /** Anzeigename des Empfängers ohne Benutzer-ID: Mailadresse oder „Konto“. */
    public function recipientLabel(): string {
        return str_starts_with($this->recipient, 'mail:') ? substr($this->recipient, 5) : (string) __('club.my.label.recipient_account');
    }
}
