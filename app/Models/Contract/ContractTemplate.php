<?php
/*
 * Created on   : Fri Sep 25 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ContractTemplate.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Models\Contract;

use App\Enums\Contract\{ContractKind, ContractTermKind, IndexationMethod};
use App\Models\Concerns\{Auditable, BelongsToOrganization, HasSqid};
use Database\Factories\Contract\ContractTemplateFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Vertragsvorlage (Feature 079, MVP-893): Laufzeit, Kündigung, Verlängerung
 * und Pflichten `{kind, title, offset_months, warn_days_before, recurring,
 * recurrence_months}` relativ zum Vertragsbeginn. Entsteht aus einem
 * Vertrag oder aus einem Branchenprofil.
 *
 * @property int $id
 * @property int $organization_id
 * @property string $name
 * @property ContractKind $kind
 * @property string|null $title
 * @property ContractTermKind|null $term_kind
 * @property int|null $min_term_months
 * @property bool $auto_renew
 * @property int|null $renew_period_months
 * @property int|null $notice_period_days
 * @property string|null $value_period
 * @property IndexationMethod|null $indexation_method
 * @property string|null $note
 * @property list<array<string, mixed>> $obligations
 * @property bool $is_active
 */
class ContractTemplate extends Model {
    use Auditable;
    use BelongsToOrganization;
    /** @use HasFactory<ContractTemplateFactory> */
    use HasFactory;
    use HasSqid;

    protected $fillable = [
        'organization_id',
        'name',
        'kind',
        'title',
        'term_kind',
        'min_term_months',
        'auto_renew',
        'renew_period_months',
        'notice_period_days',
        'value_period',
        'indexation_method',
        'note',
        'obligations',
        'is_active',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'kind' => ContractKind::class,
        'term_kind' => ContractTermKind::class,
        'indexation_method' => IndexationMethod::class,
        'min_term_months' => 'integer',
        'renew_period_months' => 'integer',
        'notice_period_days' => 'integer',
        'auto_renew' => 'boolean',
        'obligations' => 'array',
        'is_active' => 'boolean',
    ];
}
