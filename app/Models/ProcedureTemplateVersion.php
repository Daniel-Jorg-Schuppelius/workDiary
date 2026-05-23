<?php
/*
 * Created on   : Sat May 23 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ProcedureTemplateVersion.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Models;

use App\Enums\Procedure\ProcedureRiskLevel;
use Database\Factories\ProcedureTemplateVersionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Version einer {@see ProcedureTemplate}; nach Publish unveraenderlich
 * (MVP-025 §3.2/§6).
 *
 * @property int $id
 * @property int $procedure_template_id
 * @property int $version
 * @property \Illuminate\Support\Carbon|null $valid_from
 * @property \Illuminate\Support\Carbon|null $valid_to
 * @property string|null $change_note
 * @property \Illuminate\Support\Carbon|null $published_at
 * @property int|null $published_by_user_id
 * @property ProcedureRiskLevel $risk_level
 * @property array<string, mixed>|null $applicability
 */
class ProcedureTemplateVersion extends Model {
    /** @use HasFactory<ProcedureTemplateVersionFactory> */
    use HasFactory;

    protected $fillable = [
        'procedure_template_id',
        'version',
        'valid_from',
        'valid_to',
        'change_note',
        'published_at',
        'published_by_user_id',
        'risk_level',
        'applicability',
    ];

    protected $casts = [
        'valid_from' => 'date',
        'valid_to' => 'date',
        'published_at' => 'datetime',
        'risk_level' => ProcedureRiskLevel::class,
        'applicability' => 'array',
    ];

    /** @return BelongsTo<ProcedureTemplate, $this> */
    public function template(): BelongsTo {
        return $this->belongsTo(ProcedureTemplate::class, 'procedure_template_id');
    }

    /** @return HasMany<ProcedureStepDef, $this> */
    public function steps(): HasMany {
        return $this->hasMany(ProcedureStepDef::class)->orderBy('sort_order');
    }

    /** @return BelongsTo<User, $this> */
    public function publishedBy(): BelongsTo {
        return $this->belongsTo(User::class, 'published_by_user_id');
    }

    public function isPublished(): bool {
        return $this->published_at !== null;
    }

    public function isCurrent(): bool {
        if (! $this->isPublished()) {
            return false;
        }
        $today = now()->toDateString();
        $from = $this->valid_from?->toDateString();
        $to = $this->valid_to?->toDateString();
        return ($from === null || $from <= $today)
            && ($to === null || $to >= $today);
    }
}
