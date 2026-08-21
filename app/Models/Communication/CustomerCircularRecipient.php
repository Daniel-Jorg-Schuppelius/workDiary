<?php
/*
 * Created on   : Thu Aug 21 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : CustomerCircularRecipient.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Models\Communication;

use App\Models\{CommunicationNote, Customer};
use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Nachweis je Empfänger (Feature 119, MVP-608).
 *
 * Die Zeile entsteht auch für **übersprungene** Empfänger: „nicht erreicht,
 * weil keine Adresse" ist die wichtigere Information als „versendet" — nur so
 * fällt auf, dass ein Teil des Kundenkreises die Mitteilung nie gesehen hat.
 */
class CustomerCircularRecipient extends Model {
    use BelongsToOrganization;

    public const STATUS_PENDING = 'pending';

    public const STATUS_SENT = 'sent';

    public const STATUS_SKIPPED = 'skipped';

    public const STATUS_FAILED = 'failed';

    protected $fillable = [
        'organization_id',
        'customer_circular_id',
        'customer_id',
        'email',
        'status',
        'reason',
        'sent_at',
        'communication_note_id',
    ];

    /** @var array<string, string> */
    protected $casts = ['sent_at' => 'datetime'];

    /** @return BelongsTo<CustomerCircular, $this> */
    public function circular(): BelongsTo {
        return $this->belongsTo(CustomerCircular::class, 'customer_circular_id');
    }

    /** @return BelongsTo<Customer, $this> */
    public function customer(): BelongsTo {
        return $this->belongsTo(Customer::class);
    }

    /** @return BelongsTo<CommunicationNote, $this> */
    public function note(): BelongsTo {
        return $this->belongsTo(CommunicationNote::class, 'communication_note_id');
    }
}
