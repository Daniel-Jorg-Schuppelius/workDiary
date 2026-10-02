<?php
/*
 * Created on   : Sat Oct 03 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : EbicsConnectionEvent.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Models\Finance;

use App\Models\Journal\JournalEntry;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Journal eines EBICS-Zugangs (MVP-124): Einrichtungsschritte, Abrufe, Einreichungen, Fehler. */
class EbicsConnectionEvent extends JournalEntry {
    public $timestamps = false;

    protected $table = 'ebics_connection_events';

    protected $fillable = ['ebics_connection_id', 'event', 'actor_user_id', 'payload', 'occurred_at'];

    /** @var array<string, string> */
    protected $casts = [
        'payload' => 'array',
        'occurred_at' => 'datetime',
    ];

    /** @var array<string, string|null> */
    protected static array $journalColumns = ['occurred_at' => 'occurred_at'];

    /** @return BelongsTo<EbicsConnection, $this> */
    public function subject(): BelongsTo {
        return $this->belongsTo(EbicsConnection::class, 'ebics_connection_id');
    }
}
