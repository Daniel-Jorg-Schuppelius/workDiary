<?php
/*
 * Created on   : Sun Aug 02 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : DataMediaTreatment.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Models\Disposal;

use App\Enums\Disposal\{DataMediumType, DinCategory, MediaTreatmentMethod};
use App\Models\Concerns\HasSqid;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Datenträger-Behandlung einer Geräteposition (Feature 100, MVP-475):
 * dokumentiert Verfahren, DIN-66399-Kategorie + Sicherheitsstufe,
 * Schutzklasse, Zeitpunkt, Durchführenden und Beleg-Referenz. Pflicht,
 * sobald die Position `has_data_storage` trägt (Abschluss-Gate).
 * Mandantengrenze transitiv über disposal_jobs.
 *
 * @property int $id
 * @property int $disposal_item_id
 * @property DataMediumType $media_type
 * @property MediaTreatmentMethod $method
 * @property DinCategory $din_category
 * @property int $security_level
 * @property int|null $protection_class
 * @property \Illuminate\Support\Carbon $treated_at
 * @property int $performed_by_user_id
 * @property string|null $evidence_reference
 */
class DataMediaTreatment extends Model {
    use HasSqid;

    protected $fillable = [
        'disposal_item_id', 'media_type', 'method', 'din_category',
        'security_level', 'protection_class', 'treated_at',
        'performed_by_user_id', 'evidence_reference',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'media_type' => DataMediumType::class,
        'method' => MediaTreatmentMethod::class,
        'din_category' => DinCategory::class,
        'security_level' => 'integer',
        'protection_class' => 'integer',
        'treated_at' => 'datetime',
    ];

    /** @return BelongsTo<DisposalItem, $this> */
    public function item(): BelongsTo {
        return $this->belongsTo(DisposalItem::class, 'disposal_item_id');
    }

    /** @return BelongsTo<User, $this> */
    public function performer(): BelongsTo {
        return $this->belongsTo(User::class, 'performed_by_user_id');
    }

    /** Norm-Angabe der Vernichtung, z. B. "H-5". */
    public function dinLevel(): string {
        return $this->din_category->value . '-' . $this->security_level;
    }
}
