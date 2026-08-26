<?php
/*
 * Created on   : Tue Aug 25 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ProcedureDocumentation.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Models\Finance;

use App\Enums\Finance\ProcedureDocumentationStatus;
use App\Models\Concerns\{Auditable, BelongsToOrganization, HasSqid};
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use RuntimeException;

/**
 * Eine Version der GoBD-Verfahrensdokumentation (Feature 134, MVP-699):
 * Freitext-Pflichtteile des Betreibers plus — ab Veröffentlichung — der
 * eingefrorene Snapshot des generierten Systemteils und der SHA-256 des
 * erzeugten PDFs. Veröffentlichte Versionen sind unveränderlich (Guard);
 * eine Folgeversion übernimmt die Freitexte als Vorbelegung.
 *
 * @property int $id
 * @property int $organization_id
 * @property int $version
 * @property ProcedureDocumentationStatus $status
 * @property string|null $general_description
 * @property string|null $user_documentation
 * @property string|null $technical_documentation
 * @property string|null $operational_documentation
 * @property string|null $change_history
 * @property array<string, mixed>|null $snapshot
 * @property string|null $snapshot_sha256
 * @property string|null $pdf_path
 * @property string|null $pdf_sha256
 * @property Carbon|null $published_at
 * @property int|null $published_by
 * @property int|null $created_by_user_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read User|null $publishedBy
 * @property-read User|null $createdBy
 */
class ProcedureDocumentation extends Model {
    use Auditable;
    use BelongsToOrganization;
    use HasSqid;

    /** Freitext-Pflichtteile nach GoBD Rz. 151 ff. (Reihenfolge = Dokument). */
    public const TEXT_FIELDS = [
        'general_description',
        'user_documentation',
        'technical_documentation',
        'operational_documentation',
        'change_history',
    ];

    protected $table = 'procedure_documentations';

    protected $fillable = [
        'organization_id',
        'version',
        'status',
        'general_description',
        'user_documentation',
        'technical_documentation',
        'operational_documentation',
        'change_history',
        'snapshot',
        'snapshot_sha256',
        'pdf_path',
        'pdf_sha256',
        'published_at',
        'published_by',
        'created_by_user_id',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'version' => 'integer',
        'status' => ProcedureDocumentationStatus::class,
        'snapshot' => 'array',
        'published_at' => 'datetime',
    ];

    /** Snapshot ist groß und über snapshot_sha256 belegt — nicht ins Audit-Log/toArray (Auditable merged getHidden()). */
    protected $hidden = ['snapshot'];

    protected static function booted(): void {
        // Veröffentlichte Version = Nachweis: keine Änderung, keine Löschung.
        static::updating(function (self $document): void {
            if ($document->getOriginal('status') === ProcedureDocumentationStatus::Published) {
                throw new RuntimeException('Veröffentlichte Verfahrensdokumentation ist unveränderlich.');
            }
        });
        static::deleting(function (self $document): void {
            if ($document->status === ProcedureDocumentationStatus::Published) {
                throw new RuntimeException('Veröffentlichte Verfahrensdokumentation darf nicht gelöscht werden.');
            }
        });
    }

    public function displayVersion(): string {
        return 'v' . $this->version;
    }

    public function isPublished(): bool {
        return $this->status === ProcedureDocumentationStatus::Published;
    }

    public function isEditable(): bool {
        return $this->status === ProcedureDocumentationStatus::Draft;
    }

    /** @return BelongsTo<User, $this> */
    public function publishedBy(): BelongsTo {
        return $this->belongsTo(User::class, 'published_by');
    }

    /** @return BelongsTo<User, $this> */
    public function createdBy(): BelongsTo {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }
}
