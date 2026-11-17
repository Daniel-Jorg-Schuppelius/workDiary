<?php
/*
 * Created on   : Thu Nov 12 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : CustomerDeadlineEvent.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Models\Customer;

use App\Models\Journal\JournalEntry;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Verlauf einer Kundenfrist (Feature 197, MVP-1632): angelegt, neu berechnet,
 * erledigt (von Hand oder über den Auftrag), verlängert, vorangefordert,
 * entfällt, wieder geöffnet. Labels unter `journal.customer.<event>`.
 */
class CustomerDeadlineEvent extends JournalEntry {
    public $timestamps = false;

    protected $table = 'customer_deadline_events';

    protected $fillable = ['customer_deadline_id', 'event', 'actor_user_id', 'payload', 'occurred_at'];

    /** @var array<string, string> */
    protected $casts = [
        'payload' => 'array',
        'occurred_at' => 'datetime',
    ];

    /** @var array<string, string|null> */
    protected static array $journalColumns = ['occurred_at' => 'occurred_at'];

    /** @return BelongsTo<CustomerDeadline, $this> */
    public function subject(): BelongsTo {
        return $this->belongsTo(CustomerDeadline::class, 'customer_deadline_id');
    }
}
