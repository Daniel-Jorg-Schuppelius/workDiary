<?php
/*
 * Created on   : Thu Nov 12 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ServiceTemplate.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Models\ServicePlan;

use App\Enums\Period\PeriodKind;
use App\Models\Classification\EntryType;
use App\Models\Concerns\{Auditable, BelongsToOrganization, HasSqid, HasTranslatedText};
use App\Models\Platform\User;
use App\Services\ServicePlan\Dto\ServiceKey;
use Database\Factories\ServicePlan\ServiceTemplateFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Leistungsvorlage einer Organisation (Feature 199, MVP-1674): Leistung
 * (Eintragsart + Produktgruppe), Periodenart, Vorgabe Pflicht oder Wahl und
 * Bezeichnung in fünf Sprachen. Kommt aus dem Branchenprofil (Schlüssel
 * `service_catalog`, ergänzt nur) und benennt die Spalten des Leistungsplans;
 * der Dialog „Kunde anlegen“ schlägt die Vorlagen als Leistungsumfang vor.
 *
 * @property int $id
 * @property int $organization_id
 * @property int $entry_type_id
 * @property string|null $product_group_code
 * @property PeriodKind $period_kind
 * @property bool $is_mandatory
 * @property string $label
 * @property array<string, string>|null $label_i18n
 * @property int $sort
 * @property int|null $created_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class ServiceTemplate extends Model {
    use Auditable;
    use BelongsToOrganization;
    /** @use HasFactory<ServiceTemplateFactory> */
    use HasFactory;
    use HasSqid;
    use HasTranslatedText;

    protected $fillable = [
        'organization_id',
        'entry_type_id',
        'product_group_code',
        'period_kind',
        'is_mandatory',
        'label',
        'label_i18n',
        'sort',
        'created_by',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'period_kind' => PeriodKind::class,
        'is_mandatory' => 'boolean',
        'label_i18n' => 'array',
        'sort' => 'integer',
    ];

    /** @return BelongsTo<EntryType, $this> */
    public function entryType(): BelongsTo {
        return $this->belongsTo(EntryType::class);
    }

    /** @return BelongsTo<User, $this> */
    public function creator(): BelongsTo {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function serviceKey(): ServiceKey {
        return new ServiceKey((int) $this->entry_type_id, $this->product_group_code);
    }

    /** Bezeichnung in der aktiven Sprache, sonst die deutsche Quellbezeichnung. */
    public function displayLabel(?string $locale = null): string {
        return $this->translatedText(is_array($this->label_i18n) ? $this->label_i18n : null, $this->label, $locale);
    }
}
