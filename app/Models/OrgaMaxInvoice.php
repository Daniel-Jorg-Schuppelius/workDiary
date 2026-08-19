<?php
/*
 * Created on   : Wed Aug 19 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : OrgaMaxInvoice.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Models;

use App\Casts\MoneyCast;
use App\Models\Concerns\{BelongsToOrganization, HasSqid};
use Illuminate\Database\Eloquent\{Builder, Model};
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Lokaler Spiegel einer orgaMAX-Rechnung (Feature 077-Fix, MVP-653) —
 * Gegenstück zu {@see LexofficeVoucher}. Wird von der Belegprojektion des
 * Plugins aktualisiert; je Organisation eindeutig über die `external_id`.
 *
 * Der Spiegel ersetzt die frühere Ablage als Payload einer einzigen
 * {@see ExternalReference} an der Verbindung: der Unique-Index
 * `extref_unique` erlaubt je Zielmodell nur EINE Referenz, wodurch ab der
 * zweiten Rechnung ein Constraint-Fehler auftrat. Jede Rechnung hat jetzt
 * ihr eigenes lokales Objekt — und die Historie bleibt nach einem
 * Buchhaltungswechsel unabhängig von der API lesbar.
 *
 * @property string $external_id
 */
class OrgaMaxInvoice extends Model {
    use BelongsToOrganization;
    use HasSqid;

    // Ohne Angabe leitet Eloquent `orga_max_invoices` ab (wie bei
    // {@see OrgaMaxConnection}).
    protected $table = 'orgamax_invoices';

    protected $fillable = [
        'organization_id',
        'external_id',
        'customer_id',
        'customer_external_id',
        'customer_name',
        'invoice_type',
        'invoice_status',
        'invoice_number',
        'invoice_date',
        'due_on',
        'total_net',
        'total_gross',
        'outstanding_amount',
        'currency',
        'payload',
        'synced_at',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'invoice_date' => 'date',
        'due_on' => 'date',
        'total_net' => MoneyCast::class . ':currency,2',
        'total_gross' => MoneyCast::class . ':currency,2',
        'outstanding_amount' => MoneyCast::class . ':currency,2',
        'payload' => 'array',
        'synced_at' => 'datetime',
    ];

    /** @return BelongsTo<Customer, $this> */
    public function customer(): BelongsTo {
        return $this->belongsTo(Customer::class);
    }

    /**
     * Noch nicht ausgeglichene Belege (Grundlage der Wechsel-Blocker).
     *
     * @param  Builder<$this>  $query
     */
    public function scopeOpen(Builder $query): void {
        $query->whereNotIn('invoice_status', ['paid', 'cancelled', 'draft']);
    }
}
