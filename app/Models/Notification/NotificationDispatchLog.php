<?php
/*
 * Created on   : Wed Jun 10 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : NotificationDispatchLog.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Models\Notification;

use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Model;

/**
 * Append-only Dedup-/Nachweis-Log des Fristen-Scanners (MVP-018):
 * pro (Organisation, Ereignis, Subjekt, Stufe) genau ein Eintrag, der
 * Unique-Key der Tabelle erzwingt das auch bei parallelen Läufen.
 *
 * Bewusst ohne Auditable/SoftDeletes — das Log ist selbst der Nachweis
 * und wird nie verändert oder gelöscht.
 *
 * @property int $id
 * @property int $organization_id
 * @property string $event
 * @property string $subject_type
 * @property int $subject_id
 * @property string $stage
 * @property int $recipient_count
 */
class NotificationDispatchLog extends Model {
    use BelongsToOrganization;

    public const STAGE_INITIAL = 'initial';

    public const STAGE_ESCALATION = 'escalation';

    protected $table = 'notification_dispatch_log';

    protected $fillable = [
        'organization_id',
        'event',
        'subject_type',
        'subject_id',
        'stage',
        'recipient_count',
        'acknowledged_at',
        'acknowledged_by',
    ];

    /** @var array<string, string> */
    protected $casts = ['acknowledged_at' => 'datetime'];
}
