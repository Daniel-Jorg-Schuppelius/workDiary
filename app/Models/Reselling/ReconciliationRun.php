<?php
/*
 * Created on   : Thu Sep 03 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ReconciliationRun.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Models\Reselling;

use App\Enums\Reselling\ReconciliationRunStatus;
use App\Models\Concerns\{BelongsToOrganization, HasSqid};
use App\Models\{Organization, User};
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

/**
 * Ein Lauf des Lizenz-Reselling-Abgleichs (Feature 151) aus der Oberfläche.
 * Die hochgeladenen Exporte liegen auf der privaten Disk unter
 * `reselling/{org}/{run}/`; der Bericht ist das serialisierte Ergebnis.
 *
 * @property int $id
 * @property int $organization_id
 * @property int|null $created_by_user_id
 * @property ReconciliationRunStatus $status
 * @property CarbonImmutable $reference_date
 * @property int $window_before
 * @property int $window_after
 * @property bool $strict_products
 * @property list<array{kind: string, name: string, path: string}> $files
 * @property array<string, mixed>|null $summary
 * @property array<string, mixed>|null $report
 * @property string|null $error
 * @property CarbonImmutable|null $started_at
 * @property CarbonImmutable|null $finished_at
 */
class ReconciliationRun extends Model {
    use BelongsToOrganization;
    use HasSqid;

    public const DISK = 'local';

    public const KIND_TELEKOM = 'telekom';

    public const KIND_QUALITYHOSTING = 'qualityhosting';

    public const KIND_PRICELIST = 'pricelist';

    public const KIND_MAP = 'map';

    protected $table = 'reselling_reconciliation_runs';

    protected $fillable = [
        'organization_id',
        'created_by_user_id',
        'status',
        'reference_date',
        'window_before',
        'window_after',
        'strict_products',
        'files',
        'summary',
        'report',
        'error',
        'started_at',
        'finished_at',
    ];

    protected $casts = [
        'status' => ReconciliationRunStatus::class,
        'reference_date' => 'immutable_date',
        'window_before' => 'integer',
        'window_after' => 'integer',
        'strict_products' => 'boolean',
        'files' => 'array',
        'summary' => 'array',
        'report' => 'array',
        'started_at' => 'immutable_datetime',
        'finished_at' => 'immutable_datetime',
    ];

    protected static function booted(): void {
        static::deleting(static function (self $run): void {
            $run->deleteFiles();
        });
    }

    /**
     * @return BelongsTo<Organization, $this>
     */
    public function organization(): BelongsTo {
        return $this->belongsTo(Organization::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    /**
     * @return list<array{kind: string, name: string, path: string}>
     */
    public function filesOfKind(string $kind): array {
        return array_values(array_filter($this->files ?? [], static fn(array $file): bool => $file['kind'] === $kind));
    }

    public function storageDirectory(): string {
        return 'reselling/' . $this->organization_id . '/' . $this->id;
    }

    public function deleteFiles(): void {
        $disk = Storage::disk(self::DISK);
        if ($disk->exists($this->storageDirectory())) {
            $disk->deleteDirectory($this->storageDirectory());
        }
    }
}
