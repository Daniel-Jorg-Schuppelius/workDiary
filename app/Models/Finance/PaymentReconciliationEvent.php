<?php
/*
 * Created on   : Sat Jun 13 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : PaymentReconciliationEvent.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Models\Finance;

use App\Models\Concerns\{HashChainable, HashChained};
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Append-only Ereignisprotokoll des Zahlungsabgleichs (Feature 045,
 * „Datenschutz, Sicherheit und Aufbewahrung"): jede Zuordnungsaktion
 * (confirm/unmatch/ignore/unassignable) wird revisionssicher über die
 * {@see HashChained}-Hash-Kette protokolliert (registriert in
 * config('audit.chains'), prüfbar via `audit:verify`).
 *
 * Ohne BelongsToOrganization (analog BillingTransferEvent / Whistleblowing\
 * CaseEvent): die Kette muss scope-frei über alle Zeilen verifizierbar sein und
 * Einträge überdauern die Löschung von Umsatz/Organisation (organization_id geht
 * in den Hash ein, ein FK-Cascade würde die Kette zerreißen). Der payload
 * enthält bewusst KEINE PII (keine IBANs/Namen/Zwecke im Klartext).
 *
 * @property string|null $prev_hash
 * @property string|null $hash
 *
 * @phpstan-consistent-constructor
 */
class PaymentReconciliationEvent extends Model implements HashChainable {
    /** @use HasFactory<\Database\Factories\Finance\PaymentReconciliationEventFactory> */
    use HasFactory;
    use HashChained;

    protected $table = 'payment_reconciliation_events';

    public $timestamps = false;

    protected $fillable = [
        'organization_id',
        'bank_transaction_id',
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
            'bank_transaction_id' => $this->nullableInt($this->getAttribute('bank_transaction_id')),
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
