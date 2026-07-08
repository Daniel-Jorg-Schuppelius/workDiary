<?php
/*
 * Created on   : Mon Jul 06 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : AttendanceTerminal.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Models;

use App\Models\Concerns\{Auditable, BelongsToOrganization, HasSqid};
use CommonToolkit\Helper\Data\CryptoHelper;
use Illuminate\Database\Eloquent\{Builder, Model};
use Illuminate\Database\Eloquent\Factories\{Factory, HasFactory};
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/**
 * Hardware-Stempelterminal einer Organisation (Feature 061, MVP-130). Der
 * Ingest-Endpunkt wird über einen Gerätetoken im Pfad autorisiert; persistiert
 * wird nur der SHA-256-Hash (Klartext einmalig bei Ausstellung). `last_seen_at`
 * dient dem Gesundheitsstatus (Terminalausfall sichtbar).
 *
 * @property int $id
 * @property int $organization_id
 * @property int|null $site_id
 * @property string $name
 * @property string $token_hash
 * @property bool $active
 * @property int|null $created_by
 * @property \Illuminate\Support\Carbon|null $last_seen_at
 */
class AttendanceTerminal extends Model {
    use Auditable;
    use BelongsToOrganization;
    /** @use HasFactory<Factory<static>> */
    use HasFactory;

    use HasSqid;

    protected $table = 'attendance_terminals';

    protected $fillable = [
        'organization_id',
        'site_id',
        'name',
        'token_hash',
        'active',
        'last_seen_at',
        'created_by',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'active' => 'boolean',
        'last_seen_at' => 'datetime',
    ];

    public static function hashToken(string $plain): string {
        return CryptoHelper::hash($plain);
    }

    /**
     * Registriert ein Terminal mit frischem Gerätetoken und gibt [Model, Klartext]
     * zurück; der Klartext ist danach nicht mehr rekonstruierbar.
     *
     * @return array{0: self, 1: string}
     */
    public static function issue(int $organizationId, string $name, ?int $siteId = null, ?int $createdBy = null): array {
        $plain = 'term_' . Str::random(48);

        $terminal = static::query()->create([
            'organization_id' => $organizationId,
            'site_id' => $siteId,
            'name' => $name,
            'token_hash' => static::hashToken($plain),
            'created_by' => $createdBy,
        ]);

        return [$terminal, $plain];
    }

    public function isActive(): bool {
        return $this->active;
    }

    /** @param Builder<AttendanceTerminal> $query */
    public function scopeActive(Builder $query): void {
        $query->where('active', true);
    }

    /** @return BelongsTo<Site, $this> */
    public function site(): BelongsTo {
        return $this->belongsTo(Site::class);
    }
}
