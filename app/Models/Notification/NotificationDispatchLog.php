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

use App\Enums\Notification\SmsDeliveryStatus;
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
 * Seit Feature 147 (MVP-730) trägt dieselbe Tabelle auch den Zustellstatus
 * des SMS-Kanals: eine Zeile je (Alarm, Empfänger) mit der Stufe
 * `sms:<stufe>:<userId>` ({@see smsStageFor()}). Der bestehende Unique-Key ist damit
 * gleichzeitig der Doppelversand-Schutz — dieselbe Alarmierung kostet nie
 * zweimal Geld —, und die internen Stufen (initial/escalation…) bleiben von
 * den SMS-Zeilen unberührt, weil sie nie auf `sms:*` abfragen.
 * Der Nachrichtentext steht bewusst NICHT hier (Feature 147, DSGVO).
 *
 * @property int $id
 * @property int $organization_id
 * @property string $event
 * @property string $subject_type
 * @property int $subject_id
 * @property string $stage
 * @property int $recipient_count
 * @property string|null $channel
 * @property int|null $recipient_user_id
 * @property string|null $provider
 * @property string|null $provider_message_id
 * @property SmsDeliveryStatus|null $status
 * @property string|null $error_code
 * @property int $segments
 * @property \Illuminate\Support\Carbon|null $status_at
 * @property \Illuminate\Support\Carbon|null $created_at
 */
class NotificationDispatchLog extends Model {
    use BelongsToOrganization;

    public const STAGE_INITIAL = 'initial';

    public const STAGE_ESCALATION = 'escalation';

    /** Eskalationsleiter Stufe 2/3 (MVP-331): eigene Dedup-Stufen je Regel. */
    public const STAGE_ESCALATION2 = 'escalation2';

    public const STAGE_ESCALATION3 = 'escalation3';

    /** Kanal-Marker der SMS-Zeilen (Feature 147). */
    public const CHANNEL_SMS = 'sms';

    /** Stufen-Präfix der SMS-Zeilen — je Empfänger genau eine je Alarm. */
    public const STAGE_SMS_PREFIX = 'sms:';

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
        'channel',
        'recipient_user_id',
        'provider',
        'provider_message_id',
        'status',
        'error_code',
        'segments',
        'status_at',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'acknowledged_at' => 'datetime',
        'status_at' => 'datetime',
        'status' => SmsDeliveryStatus::class,
        'segments' => 'integer',
    ];

    /**
     * Dedup-Stufe der SMS-Zeile: der Unique-Key (Org, Ereignis, Subjekt,
     * Stufe) macht daraus „höchstens eine SMS je Alarm, Stufe und Empfänger".
     * Die Grundstufe steckt mit drin, damit eine Eskalation dieselbe Person
     * erneut erreicht — sonst hinge die zweite Alarmierung am Dedup der ersten.
     */
    public static function smsStageFor(int $userId, string $stage = self::STAGE_INITIAL): string {
        return self::STAGE_SMS_PREFIX . $stage . ':' . $userId;
    }
}
