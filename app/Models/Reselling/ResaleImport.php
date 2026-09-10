<?php
/*
 * Created on   : Fri Sep 04 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ResaleImport.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Models\Reselling;

use App\Enums\Reselling\{ImportStatus, SubscriptionProvider};
use App\Models\Concerns\{BelongsToOrganization, HasSqid};
use App\Models\{Organization, User};
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\{BelongsTo, HasMany};
use Illuminate\Support\Facades\Storage;

/**
 * Ein Import-Lauf des Reselling-Registers (Feature 152): eine Anbieterdatei
 * (Telekom-Käufe, Quality-Hosting-Verträge, Preisliste) mit Zählern und
 * Zeilenbefunden. Die abgelegte Datei (Endkunden-PII) hängt am Datensatz:
 * `resale:prune-imports` räumt sie nach der Frist, Löschen nimmt sie mit.
 *
 * @property int $id
 * @property int $organization_id
 * @property int|null $created_by_user_id
 * @property SubscriptionProvider $provider
 * @property string $kind
 * @property string $file_name
 * @property string|null $file_path
 * @property ImportStatus $status
 * @property int $rows_total
 * @property int $rows_created
 * @property int $rows_updated
 * @property int $rows_unchanged
 * @property int $rows_unassigned
 * @property list<string>|null $issues
 * @property string|null $error
 * @property CarbonImmutable $created_at
 */
class ResaleImport extends Model {
    use BelongsToOrganization;
    use HasSqid;

    public const DISK = 'local';

    public const KIND_PURCHASES = 'purchases';
    public const KIND_CONTRACTS = 'contracts';
    public const KIND_PRICELIST = 'pricelist';

    public const KIND_GENERIC = 'generic';

    protected $table = 'resale_imports';

    protected $fillable = [
        'organization_id',
        'created_by_user_id',
        'provider',
        'kind',
        'file_name',
        'file_path',
        'status',
        'rows_total',
        'rows_created',
        'rows_updated',
        'rows_unchanged',
        'rows_unassigned',
        'issues',
        'error',
    ];

    protected $casts = [
        'provider' => SubscriptionProvider::class,
        'status' => ImportStatus::class,
        'rows_total' => 'integer',
        'rows_created' => 'integer',
        'rows_updated' => 'integer',
        'rows_unchanged' => 'integer',
        'rows_unassigned' => 'integer',
        'issues' => 'array',
        'created_at' => 'immutable_datetime',
        'updated_at' => 'immutable_datetime',
    ];

    protected static function booted(): void {
        static::deleting(static function (self $import): void {
            $import->removeStoredFile();
        });
    }

    /** @return BelongsTo<Organization, $this> */
    public function organization(): BelongsTo {
        return $this->belongsTo(Organization::class);
    }

    /** @return BelongsTo<User, $this> */
    public function creator(): BelongsTo {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    /** @return HasMany<ResaleSubscription, $this> */
    public function subscriptions(): HasMany {
        return $this->hasMany(ResaleSubscription::class, 'import_id');
    }

    public function kindLabel(): string {
        return (string) __('resale.import.kind.' . $this->kind);
    }

    /** Zeilenbefunde (unlesbare oder abgelehnte Zeilen) — importiert wurde trotzdem. */
    public function issueCount(): int {
        return count($this->issues ?? []);
    }

    /**
     * Die ersten Befunde für Flash, Liste und Konsole.
     *
     * @return list<string>
     */
    public function issuesPreview(int $max = 5): array {
        return array_slice(array_map('strval', $this->issues ?? []), 0, max(0, $max));
    }

    /**
     * Abgelegte Importdatei löschen und den Pfad leeren; der Datensatz mit
     * seinen Zählern bleibt. Liefert true, wenn eine Datei entfernt wurde.
     */
    public function deleteFile(): bool {
        $existed = $this->removeStoredFile();
        if ($this->file_path !== null) {
            $this->forceFill(['file_path' => null])->save();
        }

        return $existed;
    }

    /** Datei vom Datenträger nehmen, ohne den Datensatz zu schreiben. */
    private function removeStoredFile(): bool {
        $path = $this->file_path;
        if ($path === null || $path === '') {
            return false;
        }
        $disk = Storage::disk(self::DISK);
        if (! $disk->exists($path)) {
            return false;
        }
        $deleted = $disk->delete($path);
        // Ein Upload legt je Datei einen Ordner `resale/{org}/{uuid}` an — leer wieder weg.
        $directory = dirname($path);
        if ($deleted && $directory !== '.' && $disk->files($directory) === [] && $disk->directories($directory) === []) {
            $disk->deleteDirectory($directory);
        }

        return $deleted;
    }
}
