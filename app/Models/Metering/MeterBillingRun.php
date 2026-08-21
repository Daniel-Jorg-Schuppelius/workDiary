<?php
/*
 * Created on   : Thu Aug 20 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : MeterBillingRun.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Models\Metering;

use App\Models\Concerns\BelongsToOrganization;
use App\Models\Invoice;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Ein abgearbeiteter Abrechnungszeitraum (Feature 116, MVP-605).
 *
 * Die Zeile entsteht AUCH ohne Rechnung: Ein übersprungener Lauf mit Grund
 * ist die eigentliche Information — sonst sieht ein fehlender Entwurf aus wie
 * „noch nicht gelaufen" statt „Ablesung fehlt".
 */
class MeterBillingRun extends Model {
    use BelongsToOrganization;

    protected $fillable = [
        'organization_id',
        'meter_billing_agreement_id',
        'period_start',
        'period_end',
        'invoice_id',
        'skipped_reason',
        'consumption',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'period_start' => 'date',
        'period_end' => 'date',
    ];

    /** @return BelongsTo<MeterBillingAgreement, $this> */
    public function agreement(): BelongsTo {
        return $this->belongsTo(MeterBillingAgreement::class, 'meter_billing_agreement_id');
    }

    /** @return BelongsTo<Invoice, $this> */
    public function invoice(): BelongsTo {
        return $this->belongsTo(Invoice::class);
    }
}
