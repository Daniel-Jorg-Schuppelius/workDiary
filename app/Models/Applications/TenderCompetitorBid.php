<?php
/*
 * Created on   : Tue Aug 18 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : TenderCompetitorBid.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Models\Applications;

use App\Models\Concerns\{BelongsToOrganization, HasSqid};
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Ein im Eröffnungstermin verlesenes oder aus dem Informationsschreiben
 * bekanntes Angebot (Feature 108, MVP-628).
 *
 * Das eigene Angebot steht als Zeile mit `is_own` mit in der Liste — nur so
 * ist der Preisabstand ablesbar, und nur so bleibt die Reihenfolge des
 * Termins erhalten.
 *
 * @property int $id
 * @property int $organization_id
 * @property int $application_opportunity_id
 * @property string $bidder_name
 * @property string|null $amount
 * @property string $currency
 * @property int|null $rank
 * @property bool $is_own
 * @property bool $is_winner
 * @property \Illuminate\Support\Carbon|null $recorded_on
 * @property string $source
 * @property string|null $note
 */
class TenderCompetitorBid extends Model {
    use BelongsToOrganization;
    use HasSqid;

    /** Woher die Angabe stammt — beides sind amtliche Quellen, keine Gerüchte. */
    public const SOURCES = ['opening', 'information_letter', 'other'];

    protected $table = 'tender_competitor_bids';

    protected $fillable = [
        'organization_id', 'application_opportunity_id', 'bidder_name', 'amount',
        'currency', 'rank', 'is_own', 'is_winner', 'recorded_on', 'source', 'note', 'created_by',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'amount' => 'decimal:2',
        'rank' => 'integer',
        'is_own' => 'boolean',
        'is_winner' => 'boolean',
        'recorded_on' => 'date',
    ];

    /** @return BelongsTo<ApplicationOpportunity, $this> */
    public function opportunity(): BelongsTo {
        return $this->belongsTo(ApplicationOpportunity::class, 'application_opportunity_id');
    }
}
