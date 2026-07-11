<?php
/*
 * Created on   : Fri Jul 10 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : InvestmentLink.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Models\Investments;

use App\Models\Concerns\{BelongsToOrganization, HasSqid};
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\{BelongsTo, MorphTo};

/**
 * Umsetzungs-Verknüpfung (Feature 069, MVP-204): genehmigte Investition →
 * Projekt/Bestellung/Asset/Dokument/Eingangsrechnung; die führenden
 * Module bleiben führend (Datenführerschaft).
 *
 * @property int $id
 * @property int $organization_id
 * @property int $investment_case_id
 * @property string $linkable_type
 * @property int $linkable_id
 * @property string|null $note
 * @property int|null $created_by
 */
class InvestmentLink extends Model {
    use BelongsToOrganization;
    use HasSqid;

    protected $fillable = [
        'organization_id', 'investment_case_id', 'linkable_type', 'linkable_id',
        'note', 'created_by',
    ];

    /** @return BelongsTo<InvestmentCase, $this> */
    public function investmentCase(): BelongsTo {
        return $this->belongsTo(InvestmentCase::class, 'investment_case_id');
    }

    /** @return MorphTo<Model, $this> */
    public function linkable(): MorphTo {
        return $this->morphTo();
    }
}
