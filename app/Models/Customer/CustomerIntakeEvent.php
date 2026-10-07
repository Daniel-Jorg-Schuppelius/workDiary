<?php
/*
 * Created on   : Wed Oct 07 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : CustomerIntakeEvent.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Models\Customer;

use App\Models\Journal\JournalEntry;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Journal eines Kundeneingangs (MVP-1074–1077): Einreichung, Dateien, Rückfragen, Angebot, Übernahme, Mailfehler. */
class CustomerIntakeEvent extends JournalEntry {
    public $timestamps = false;

    protected $table = 'customer_intake_events';

    protected $fillable = ['customer_intake_id', 'event', 'actor_user_id', 'payload', 'occurred_at'];

    /** @var array<string, string> */
    protected $casts = [
        'payload' => 'array',
        'occurred_at' => 'datetime',
    ];

    /** @var array<string, string|null> */
    protected static array $journalColumns = ['occurred_at' => 'occurred_at'];

    /** @return BelongsTo<CustomerIntake, $this> */
    public function subject(): BelongsTo {
        return $this->belongsTo(CustomerIntake::class, 'customer_intake_id');
    }
}
