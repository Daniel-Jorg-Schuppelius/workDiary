<?php
/*
 * Created on   : Wed Jul 01 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : TimeExport.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Models;

use App\Enums\TimeExport\TimeExportStatus;
use App\Models\Concerns\{Auditable, BelongsToOrganization};
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\{BelongsTo, HasMany};
use Illuminate\Support\Carbon;

/**
 * Zeit-Export (MVP-019).
 *
 * Erzeugt aus genehmigten Monatsfreigaben eine herunterladbare Datei
 * (CSV/DATEV/Lexware) für das Lohnbüro. Re-Exporte desselben Zeitraums
 * markieren ältere Exporte als `superseded`.
 *
 * @property int $id
 * @property int $organization_id
 * @property string $profile
 * @property int $period_year
 * @property int $period_month
 * @property string $scope
 * @property int|null $scope_user_id
 * @property int|null $scope_team_id
 * @property TimeExportStatus $status
 * @property int $rows_count
 * @property array<string, mixed>|null $totals
 * @property string|null $payload_hash
 * @property string|null $file_path
 * @property string|null $file_format
 * @property int|null $created_by_user_id
 * @property Carbon|null $delivered_at
 * @property int|null $delivered_by_user_id
 * @property string|null $delivery_note
 * @property array<string, array<string, mixed>>|null $auto_delivery
 * @property int|null $superseded_by_id
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
class TimeExport extends Model {
    /**
     * Felder, die auch nach der Auslieferung noch geändert werden dürfen.
     *
     * Alles, was den ausgelieferten Stand beschreibt (Zeilen, Summen,
     * `payload_hash`, Datei), ist ab `delivered_at` gesperrt — der Hash weist
     * genau diesen Stand nach. Was danach noch dazukommt, sind Vermerke über
     * die Auslieferung selbst.
     *
     * @var list<string>
     */
    public const MUTABLE_AFTER_DELIVERY = [
        'delivery_note',
        'delivered_at',
        'delivered_by_user_id',
        'status',
        'updated_at',
    ];

    /**
     * Unveränderlich ab Auslieferung (Sicherheitsscan 2026-08-23, S-59).
     *
     * Die Unveränderlichkeit stand bisher nur im `TimeExportService`; ein
     * Schreibpfad, der am Dienst vorbeigeht, konnte Zeilen und Summen einer
     * bereits an das Lohnbüro übergebenen Ausleitung ändern — der
     * `payload_hash` hätte dann etwas anderes nachgewiesen als die Datei.
     */
    protected static function booted(): void {
        static::updating(function (self $export): void {
            if ($export->getRawOriginal('delivered_at') === null) {
                return; // noch nicht ausgeliefert
            }

            $blocked = array_diff(array_keys($export->getDirty()), self::MUTABLE_AFTER_DELIVERY);
            if ($blocked !== []) {
                throw new \RuntimeException(
                    'Ausgelieferte Zeitexporte sind unveränderlich (Felder: ' . implode(', ', $blocked) . ').',
                );
            }
        });

        static::deleting(function (self $export): void {
            if ($export->getRawOriginal('delivered_at') !== null) {
                throw new \RuntimeException('Ausgelieferte Zeitexporte dürfen nicht gelöscht werden.');
            }
        });
    }

    use Auditable;
    use BelongsToOrganization;

    protected $fillable = [
        'organization_id',
        'profile',
        'period_year',
        'period_month',
        'scope',
        'scope_user_id',
        'scope_team_id',
        'status',
        'rows_count',
        'totals',
        'payload_hash',
        'file_path',
        'file_format',
        'created_by_user_id',
        'delivered_at',
        'delivered_by_user_id',
        'delivery_note',
        'auto_delivery',
        'superseded_by_id',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'period_year' => 'integer',
        'period_month' => 'integer',
        'status' => TimeExportStatus::class,
        'rows_count' => 'integer',
        'totals' => 'array',
        'delivered_at' => 'datetime',
        'auto_delivery' => 'array',
    ];

    public function periodLabel(): string {
        return sprintf('%04d-%02d', $this->period_year, $this->period_month);
    }

    /** @return HasMany<TimeExportLine, $this> */
    public function lines(): HasMany {
        return $this->hasMany(TimeExportLine::class);
    }

    /** @return HasMany<TimeExportEvent, $this> */
    public function events(): HasMany {
        return $this->hasMany(TimeExportEvent::class)->orderBy('created_at');
    }

    /** @return BelongsTo<User, $this> */
    public function creator(): BelongsTo {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    /** @return BelongsTo<User, $this> */
    public function deliveredBy(): BelongsTo {
        return $this->belongsTo(User::class, 'delivered_by_user_id');
    }

    /** @return BelongsTo<User, $this> */
    public function scopeUser(): BelongsTo {
        return $this->belongsTo(User::class, 'scope_user_id');
    }

    /** @return BelongsTo<TimeExport, $this> */
    public function supersededBy(): BelongsTo {
        return $this->belongsTo(self::class, 'superseded_by_id');
    }
}
