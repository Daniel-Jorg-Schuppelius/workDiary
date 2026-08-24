<?php
/*
 * Created on   : Wed Jun 10 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : BillingTransferEvent.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Models\Finance;

use App\Models\Concerns\{HashChainable, HashChained};
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Append-only Ereignisprotokoll für Übergabenachweise (Feature 045,
 * Abschnitt „Datenschutz, Sicherheit und Aufbewahrung"): jede Statusänderung
 * eines BillingTransfers wird revisionssicher über die {@see HashChained}-
 * Hash-Kette protokolliert (registriert in config('audit.chains'), prüfbar
 * via `audit:verify`).
 *
 * Ohne BelongsToOrganization (analog Whistleblowing\CaseEvent): die Kette muss
 * scope-frei über alle Zeilen verifizierbar sein und Einträge überdauern die
 * Löschung von Transfer/Organisation (organization_id geht in den Hash ein,
 * ein FK-Cascade würde die Kette zerreißen).
 *
 * @property string|null $prev_hash
 * @property string|null $hash
 *
 * @phpstan-consistent-constructor
 */
class BillingTransferEvent extends Model implements HashChainable {
    /** @use HasFactory<\Database\Factories\Finance\BillingTransferEventFactory> */
    use HasFactory;
    use HashChained;

    protected $table = 'billing_transfer_events';

    public $timestamps = false;

    protected $fillable = [
        'organization_id',
        'billing_transfer_id',
        'event',
        'actor_user_id',
        'payload',
        'created_at',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'payload' => 'array',
        'created_at' => 'datetime',
    ];

    /**
     * In den Hash eingehende Nutzdaten (feste Reihenfolge, IDs null-erhaltend
     * normalisiert — treiberunabhängig).
     *
     * @return array<string, mixed>
     */
    public function hashPayload(): array {
        return [
            'organization_id' => $this->nullableInt($this->getAttribute('organization_id')),
            'billing_transfer_id' => $this->nullableInt($this->getAttribute('billing_transfer_id')),
            'event' => $this->getAttribute('event'),
            'actor_user_id' => $this->nullableInt($this->getAttribute('actor_user_id')),
            'payload' => $this->getAttribute('payload'),
            'created_at' => $this->hashCreatedAt(),
        ];
    }

    private function nullableInt(mixed $value): ?int {
        return $value === null ? null : (int) $value;
    }
}
