<?php
/*
 * Created on   : Wed Jun 03 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : Classification.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Models\Classification;

use App\Enums\Classification\ClassificationDomain;
use App\Models\Concerns\{Auditable, HasSqid};
use App\Models\Platform\Organization;
use Database\Factories\Classification\ClassificationFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Klassifikations-Wert (Plattform-Default oder Org-Override).
 *
 * @property int $id
 * @property int|null $organization_id NULL = Plattform-Default
 * @property ClassificationDomain $domain
 * @property string $code
 * @property string $label
 * @property array<string, string>|null $label_i18n
 * @property-read string $display_label
 * @property int $sort_order
 * @property string|null $color_hex
 * @property string|null $icon
 * @property bool $active
 * @property Carbon|null $deprecated_at
 * @property string|null $description
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
class Classification extends Model {
    use Auditable;
    /** @use HasFactory<ClassificationFactory> */
    use HasFactory;

    use HasSqid;

    protected $fillable = [
        'organization_id',
        'domain',
        'code',
        'label',
        'label_i18n',
        'sort_order',
        'color_hex',
        'icon',
        'active',
        'deprecated_at',
        'description',
    ];

    protected $casts = [
        'domain' => ClassificationDomain::class,
        'label_i18n' => 'array',
        'sort_order' => 'int',
        'active' => 'bool',
        'deprecated_at' => 'datetime',
    ];

    /** @return BelongsTo<Organization, $this> */
    public function organization(): BelongsTo {
        return $this->belongsTo(Organization::class);
    }

    public function isPlatformDefault(): bool {
        return $this->organization_id === null;
    }

    /**
     * Label in der aktiven Sprache (MVP-841): `label_i18n[locale]`, sonst die
     * Fallback-Sprache der App, sonst das Quell-Label (Deutsch). Für die
     * Anzeige; `label` bleibt der bearbeitbare Quellwert.
     */
    public function displayLabel(?string $locale = null): string {
        $locale ??= app()->getLocale();
        $i18n = is_array($this->label_i18n) ? $this->label_i18n : [];
        $short = strtolower(substr($locale, 0, 2));
        foreach (array_unique([$locale, $short]) as $key) {
            $value = trim((string) ($i18n[$key] ?? ''));
            if ($value !== '') {
                return $value;
            }
        }
        if ($short !== 'de') {
            $fallback = strtolower(substr((string) config('app.fallback_locale', 'en'), 0, 2));
            $value = trim((string) ($i18n[$fallback] ?? ''));
            if ($value !== '') {
                return $value;
            }
        }

        return (string) $this->label;
    }

    public function getDisplayLabelAttribute(): string {
        return $this->displayLabel();
    }
}
