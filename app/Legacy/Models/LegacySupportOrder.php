<?php
/*
 * Created on   : Mon Nov 09 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : LegacySupportOrder.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Legacy\Models;

use App\Legacy\Support\LegacyClock;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\{Builder, Model};
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Auftrag des Support-Portals im Altsystem (`kp_auftrag`).
 *
 * Der Status ist eine Zweierpotenz wie im Altportal; `termin` bleibt roh, weil
 * die Tabelle „kein Termin" als Datum im Jahr 2200 und Altfehler als Null-Datum
 * führt — gelesen wird über {@see appointmentAt()}.
 *
 * @property int $id
 * @property int $kunde
 * @property Carbon|null $datum
 * @property string $kname
 * @property string $telefon
 * @property string $objekt
 * @property string $auftrag
 * @property string|null $termin
 * @property string $wer
 * @property string $antwort
 * @property string $intern
 * @property string $dauer
 * @property int $status
 * @property-read LegacySupportCustomer|null $customer
 *
 * @method static Builder<static>|LegacySupportOrder newModelQuery()
 * @method static Builder<static>|LegacySupportOrder newQuery()
 * @method static Builder<static>|LegacySupportOrder query()
 * @method static Builder<static>|LegacySupportOrder overdue()
 */
class LegacySupportOrder extends Model {
    public const int STATUS_NEW = 1;

    public const int STATUS_SEEN = 2;

    public const int STATUS_SCHEDULED = 4;

    public const int STATUS_DONE = 8;

    public const int STATUS_ARCHIVED = 16;

    /** @var list<int> */
    public const array STATUSES = [self::STATUS_NEW, self::STATUS_SEEN, self::STATUS_SCHEDULED, self::STATUS_DONE, self::STATUS_ARCHIVED];

    /**
     * Status, bei denen ein verstrichener Termin weitere Bearbeitung verlangt.
     *
     * @var list<int>
     */
    public const array OPEN_STATUSES = [self::STATUS_NEW, self::STATUS_SEEN, self::STATUS_SCHEDULED];

    /** Wert, den das Altportal für „kein Termin" schreibt; die Spaltenvorgabe liegt ebenfalls im Jahr 2200. */
    public const string NO_APPOINTMENT = '2200-02-11 20:00:22';

    public const string NO_APPOINTMENT_FROM = '2200-01-01 00:00:00';

    /** Unterhalb davon steht ein Null-Datum aus dem Altbestand, kein Termin. */
    public const string VALID_APPOINTMENT_FROM = '1000-01-01 00:00:00';

    /**
     * Auftraggeber, den das Altportal bei Bürovorgängen einträgt (je Bürokonto
     * 1–3); das Feld bleibt dann in der Bearbeitung gesperrt.
     *
     * @var array<int, string>
     */
    public const array OFFICE_ORDERERS = [1 => 'chefadmin', 2 => 'bueradmin', 3 => 'nicadmin'];

    protected $connection = 'legacy';

    protected $table = 'kp_auftrag';

    public $timestamps = false;

    protected $fillable = ['kunde', 'datum', 'kname', 'telefon', 'objekt', 'auftrag', 'termin', 'wer', 'antwort', 'intern', 'dauer', 'status'];

    protected $primaryKey = 'id';

    protected function casts(): array {
        return [
            'kunde' => 'integer',
            'datum' => 'datetime',
            'status' => 'integer',
        ];
    }

    public function customer(): BelongsTo {
        return $this->belongsTo(LegacySupportCustomer::class, 'kunde', 'id');
    }

    public function appointmentAt(): ?Carbon {
        $raw = trim((string) ($this->termin ?? ''));
        if ($raw === '' || $raw < self::VALID_APPOINTMENT_FROM || $raw >= self::NO_APPOINTMENT_FROM) {
            return null;
        }

        try {
            return Carbon::parse($raw);
        } catch (\Throwable) {
            return null;
        }
    }

    /** Offener Auftrag, dessen Termin erreicht ist — im Altportal „weitere Bearbeitung nötig". */
    public function isOverdue(): bool {
        return in_array((int) $this->status, self::OPEN_STATUSES, true)
            && $this->appointmentAt() !== null
            && trim((string) $this->termin) <= LegacyClock::nowString();
    }

    public function isOfficeOrder(): bool {
        return in_array((string) $this->kname, self::OFFICE_ORDERERS, true);
    }

    /** Offene Aufträge mit erreichtem Termin; dieselbe Grenze wie {@see isOverdue()}. */
    #[Scope]
    protected function overdue(Builder $query): void {
        $query->whereIn('status', self::OPEN_STATUSES)
            ->where('termin', '>=', self::VALID_APPOINTMENT_FROM)
            ->where('termin', '<=', LegacyClock::nowString());
    }

    public static function statusLabelFor(int $status): string {
        return match ($status) {
            self::STATUS_NEW => __('legacy_support.status.new'),
            self::STATUS_SEEN => __('legacy_support.status.seen'),
            self::STATUS_SCHEDULED => __('legacy_support.status.scheduled'),
            self::STATUS_DONE => __('legacy_support.status.done'),
            self::STATUS_ARCHIVED => __('legacy_support.status.archived'),
            default => __('legacy_support.status.unknown', ['status' => $status]),
        };
    }

    public function statusLabel(): string {
        return self::statusLabelFor((int) $this->status);
    }

    public function statusTone(): string {
        return match ((int) $this->status) {
            self::STATUS_NEW => 'info',
            self::STATUS_SEEN => 'warning',
            self::STATUS_SCHEDULED => 'primary',
            self::STATUS_DONE => 'success',
            default => 'ghost',
        };
    }

    /** Die gröbere Sicht des Kunden aus dem Altportal: neu, in Bearbeitung, erledigt, archiviert. */
    public function customerStatusLabel(): string {
        $status = (int) $this->status;

        return match (true) {
            $status === self::STATUS_NEW => __('legacy_support.customer_status.new'),
            $status >= self::STATUS_ARCHIVED => __('legacy_support.customer_status.archived'),
            $status >= self::STATUS_DONE => __('legacy_support.customer_status.done'),
            default => __('legacy_support.customer_status.in_progress'),
        };
    }

    public function customerStatusTone(): string {
        $status = (int) $this->status;

        return match (true) {
            $status === self::STATUS_NEW => 'info',
            $status >= self::STATUS_ARCHIVED => 'ghost',
            $status >= self::STATUS_DONE => 'success',
            default => 'warning',
        };
    }
}
