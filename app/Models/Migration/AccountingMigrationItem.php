<?php
/*
 * Created on   : Wed Aug 19 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : AccountingMigrationItem.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Models\Migration;

use App\Enums\Migration\MigrationDataArea;
use App\Models\Concerns\{BelongsToOrganization, HasSqid};
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\{BelongsTo, MorphTo};

/**
 * Einzelner Datensatz eines Migrationslaufs (MVP-653): hält die Zuordnung
 * Quelle → lokales Fachobjekt → Ziel fest. `dedupe_key` macht jeden Schritt
 * idempotent — ein Wiederholungslauf setzt fort statt zu duplizieren.
 *
 * @property MigrationDataArea $data_area
 */
class AccountingMigrationItem extends Model {
    use BelongsToOrganization;
    /** @use HasFactory<\Database\Factories\Migration\AccountingMigrationItemFactory> */
    use HasFactory;
    use HasSqid;

    /** Erkannt, noch nicht entschieden. */
    public const STATUS_PENDING = 'pending';

    /** Eindeutig zugeordnet (beide Fremd-IDs am selben lokalen Objekt). */
    public const STATUS_MATCHED = 'matched';

    /** Im Zielsystem angelegt/verknüpft. */
    public const STATUS_TRANSFERRED = 'transferred';

    /** Mehrdeutig oder verlustbehaftet — blockiert, Entscheidung nötig. */
    public const STATUS_CONFLICT = 'conflict';

    /** Bewusst übersprungen (z. B. archivierte Quelle). */
    public const STATUS_SKIPPED = 'skipped';

    /** Read-only Historie: bleibt im Quellsystem, wird nie nachgebaut. */
    public const STATUS_HISTORIC = 'historic';

    /** Schreibversuch mit unklarem Ausgang oder Fehler. */
    public const STATUS_FAILED = 'failed';

    protected $fillable = [
        'organization_id',
        'accounting_migration_run_id',
        'data_area',
        'status',
        'source_external_id',
        'target_external_id',
        'referenceable_type',
        'referenceable_id',
        'dedupe_key',
        'display_title',
        'source_snapshot',
        'diff',
        'note',
        'decided_by',
        'decided_at',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'data_area' => MigrationDataArea::class,
        'source_snapshot' => 'array',
        'diff' => 'array',
        'decided_at' => 'datetime',
    ];

    /** @return BelongsTo<AccountingMigrationRun, $this> */
    public function run(): BelongsTo {
        return $this->belongsTo(AccountingMigrationRun::class, 'accounting_migration_run_id');
    }

    /** @return MorphTo<Model, $this> */
    public function referenceable(): MorphTo {
        return $this->morphTo();
    }

    /** Blockiert die Umschaltung? Konflikte und Fehler tun das. */
    public function blocksCutover(): bool {
        return in_array($this->status, [self::STATUS_CONFLICT, self::STATUS_FAILED], true);
    }
}
