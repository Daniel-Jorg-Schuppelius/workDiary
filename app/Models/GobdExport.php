<?php
/*
 * Created on   : Sat Jul 05 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : GobdExport.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Models;

use App\Enums\Finance\GobdExportStatus;
use App\Models\Concerns\{Auditable, BelongsToOrganization, HasSqid};
use Illuminate\Database\Eloquent\Factories\{Factory, HasFactory};
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Nachweis einer GoBD-Z3-Datenträgerüberlassung (Feature 063, MVP-132). Jede
 * Erzeugung eines Exportpakets wird hier revisionssicher festgehalten (Auditable
 * ⇒ Hash-Kette); die Datei-Hashes belegen die Unveränderlichkeit des Pakets.
 *
 * Seit MVP-722 ist die Zeile zugleich der LAUF: sie entsteht beim Einreihen in
 * die Queue und trägt Status, Ablage und Fehlertext, damit ein Paketbau
 * sichtbar ist, bevor er fertig ist.
 *
 * @property int $id
 * @property int $organization_id
 * @property \Illuminate\Support\Carbon $period_from
 * @property \Illuminate\Support\Carbon $period_to
 * @property array<int, string> $sections
 * @property array<string, string> $file_hashes
 * @property string $package_sha256
 * @property int $record_count
 * @property int|null $created_by
 * @property GobdExportStatus $status
 * @property string $encoding
 * @property string|null $file_path
 * @property int|null $file_size
 * @property string|null $error
 * @property \Illuminate\Support\Carbon|null $started_at
 * @property \Illuminate\Support\Carbon|null $finished_at
 */
class GobdExport extends Model {
    use Auditable;
    use BelongsToOrganization;
    /** @use HasFactory<Factory<static>> */
    use HasFactory;

    use HasSqid;

    protected $fillable = [
        'organization_id',
        'period_from',
        'period_to',
        'sections',
        'file_hashes',
        'package_sha256',
        'record_count',
        'created_by',
        'status',
        'encoding',
        'file_path',
        'file_size',
        'error',
        'started_at',
        'finished_at',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'period_from' => 'date',
        'period_to' => 'date',
        'sections' => 'array',
        'file_hashes' => 'array',
        'record_count' => 'integer',
        'status' => GobdExportStatus::class,
        'file_size' => 'integer',
        'started_at' => 'datetime',
        'finished_at' => 'datetime',
    ];

    /** @return BelongsTo<User, $this> */
    public function creator(): BelongsTo {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Absoluter Pfad des Pakets im privaten Speicher — NULL, solange der Lauf
     * unterwegs ist oder (Bestandszeilen aus der synchronen Zeit) nie eine
     * Datei abgelegt wurde.
     */
    public function packagePath(): ?string {
        return $this->file_path === null ? null : storage_path('app/private/' . $this->file_path);
    }

    /** Dateiname für den Download (Zeitraum statt interner Ablage-Name). */
    public function downloadName(): string {
        return 'gobd-z3-' . $this->period_from->format('Ymd') . '-' . $this->period_to->format('Ymd') . '.zip';
    }
}
