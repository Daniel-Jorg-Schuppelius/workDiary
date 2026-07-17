<?php
/*
 * Created on   : Fri Jul 17 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : InvoiceScheduleRun.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Idempotenz-Nachweis eines Abrechnungslaufs (MVP-415): je Plan und
 * Periode höchstens ein Rechnungsentwurf (DB-unique).
 *
 * @property int $id
 * @property int $organization_id
 * @property int $invoice_schedule_id
 * @property Carbon $period_start
 * @property Carbon $period_end
 * @property int|null $invoice_id
 */
class InvoiceScheduleRun extends Model {
    use BelongsToOrganization;

    protected $fillable = [
        'organization_id',
        'invoice_schedule_id',
        'period_start',
        'period_end',
        'invoice_id',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'period_start' => 'date',
        'period_end' => 'date',
    ];

    /** @return BelongsTo<InvoiceSchedule, $this> */
    public function schedule(): BelongsTo {
        return $this->belongsTo(InvoiceSchedule::class, 'invoice_schedule_id');
    }

    /** @return BelongsTo<Invoice, $this> */
    public function invoice(): BelongsTo {
        return $this->belongsTo(Invoice::class);
    }
}
