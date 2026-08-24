<?php
/*
 * Created on   : Wed Aug 19 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : AccountingMigrationRun.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Models\Migration;

use App\Enums\Migration\{AccountingMigrationStatus, MigrationDataArea, MigrationProvider};
use App\Models\Concerns\{Auditable, BelongsToOrganization, HasSqid};
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\{BelongsTo, HasMany};

/**
 * Migrationslauf eines Buchhaltungswechsels (MVP-653, Issue #86): führt
 * Quelle, Ziel, Stichtag, gewählte Datenbereiche, Zählwerke und Checkpoints.
 * Je Organisation ist höchstens ein Lauf gleichzeitig aktiv.
 *
 * @property AccountingMigrationStatus $status
 * @property array<int, string> $data_areas
 */
class AccountingMigrationRun extends Model {
    use Auditable;
    use BelongsToOrganization;
    /** @use HasFactory<\Database\Factories\Migration\AccountingMigrationRunFactory> */
    use HasFactory;
    use HasSqid;

    protected $fillable = [
        'organization_id',
        'source_plugin',
        'target_plugin',
        'status',
        'data_areas',
        'cutover_on',
        'cutover_at',
        'dry_run_only',
        'counters',
        'checkpoints',
        'preflight',
        'blocked_reason',
        'responsible_user_id',
        'completed_by',
        'completed_at',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'status' => AccountingMigrationStatus::class,
        'data_areas' => 'array',
        'counters' => 'array',
        'checkpoints' => 'array',
        'preflight' => 'array',
        'cutover_on' => 'date',
        'cutover_at' => 'datetime',
        'completed_at' => 'datetime',
        'dry_run_only' => 'boolean',
    ];

    /** @return HasMany<AccountingMigrationItem, $this> */
    public function items(): HasMany {
        return $this->hasMany(AccountingMigrationItem::class);
    }

    /** @return BelongsTo<User, $this> */
    public function responsible(): BelongsTo {
        return $this->belongsTo(User::class, 'responsible_user_id');
    }

    /**
     * Gewählte Datenbereiche als Enum-Liste.
     *
     * @return array<int, MigrationDataArea>
     */
    public function areas(): array {
        return array_values(array_filter(array_map(
            static fn (string $value): ?MigrationDataArea => MigrationDataArea::tryFrom($value),
            (array) ($this->data_areas ?? []),
        )));
    }

    public function coversArea(MigrationDataArea $area): bool {
        return in_array($area->value, (array) ($this->data_areas ?? []), true);
    }

    /** Quellsystem des Wechsels (aus dem sich alle Fremd-IDs ableiten). */
    public function source(): MigrationProvider {
        return MigrationProvider::tryFrom((string) $this->source_plugin) ?? MigrationProvider::Lexoffice;
    }

    /** Zielsystem — bestimmt Fakturahoheit und Übergabeziel nach der Umschaltung. */
    public function target(): MigrationProvider {
        return MigrationProvider::tryFrom((string) $this->target_plugin) ?? MigrationProvider::OrgaMax;
    }

    /** Stichtag erreicht — ab hier führt ausschließlich das Zielsystem. */
    public function cutoverReached(?CarbonInterface $at = null): bool {
        if ($this->cutover_on === null) {
            return false;
        }

        return ($at ?? now())->startOfDay()->greaterThanOrEqualTo($this->cutover_on->startOfDay());
    }

    /** Checkpoint eines Datenbereichs (Wiederaufnahme ohne Dubletten). */
    public function checkpoint(string $key): ?int {
        $value = ($this->checkpoints ?? [])[$key] ?? null;

        return is_numeric($value) ? (int) $value : null;
    }

    public function setCheckpoint(string $key, ?int $offset): void {
        $checkpoints = (array) ($this->checkpoints ?? []);
        if ($offset === null) {
            unset($checkpoints[$key]);
        } else {
            $checkpoints[$key] = $offset;
        }
        $this->checkpoints = $checkpoints;
        $this->save();
    }
}
