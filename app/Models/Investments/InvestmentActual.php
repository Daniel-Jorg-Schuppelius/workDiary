<?php
/*
 * Created on   : Fri Jul 10 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : InvestmentActual.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Models\Investments;

use App\Models\Concerns\{Auditable, BelongsToOrganization, HasSqid};
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\{BelongsTo, MorphTo};

/**
 * Ist-Wert (Feature 069, MVP-205): PROJEKTION aus führenden Modulen
 * (Eingangsrechnung/Bestellung/Asset) oder manueller Nachtrag — ersetzt
 * keine Buchhaltung und ändert nie den genehmigten Antrag.
 *
 * @property int $id
 * @property int $organization_id
 * @property int $investment_case_id
 * @property string $source
 * @property string|null $reference_type
 * @property int|null $reference_id
 * @property string $amount
 * @property \Illuminate\Support\Carbon $occurred_on
 * @property string|null $note
 * @property int|null $created_by
 */
class InvestmentActual extends Model {
    use Auditable;
    use BelongsToOrganization;
    use HasSqid;

    public const SOURCES = ['manual', 'incoming_invoice', 'purchase_order', 'asset', 'project'];

    protected $fillable = [
        'organization_id', 'investment_case_id', 'source', 'reference_type',
        'reference_id', 'amount', 'occurred_on', 'note', 'created_by',
    ];

    /** @var array<string, string> */
    protected $casts = ['occurred_on' => 'date', 'amount' => 'decimal:2'];

    /** @return BelongsTo<InvestmentCase, $this> */
    public function investmentCase(): BelongsTo {
        return $this->belongsTo(InvestmentCase::class, 'investment_case_id');
    }

    /** @return MorphTo<Model, $this> */
    public function reference(): MorphTo {
        return $this->morphTo();
    }
}
