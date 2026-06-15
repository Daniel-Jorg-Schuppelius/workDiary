<?php
/*
 * Created on   : Sun Jun 14 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : DatevBookingEvent.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Models\Finance;

use App\Models\Concerns\{HashChainable, HashChained};
use Illuminate\Database\Eloquent\Model;

/**
 * Append-only Ereignisprotokoll des DATEV-Buchungsexports (Feature 045,
 * „Datenschutz, Sicherheit und Aufbewahrung"): jede Statusänderung eines
 * Buchungsstapels (created/finalized) wird revisionssicher über die
 * {@see HashChained}-Hash-Kette protokolliert (registriert in
 * config('audit.chains'), prüfbar via `audit:verify`).
 *
 * Ohne BelongsToOrganization (analog BillingTransferEvent / PaymentReconciliation
 * Event): die Kette muss scope-frei über alle Zeilen verifizierbar sein und
 * Einträge überdauern die Löschung von Batch/Organisation (organization_id geht
 * in den Hash ein, ein FK-Cascade würde die Kette zerreißen). Der payload
 * enthält bewusst KEINE PII (keine Kundennamen/Belegtexte im Klartext).
 *
 * @property string|null $prev_hash
 * @property string|null $hash
 *
 * @phpstan-consistent-constructor
 */
class DatevBookingEvent extends Model implements HashChainable {
    use HashChained;

    protected $table = 'datev_booking_events';

    public $timestamps = false;

    protected $fillable = [
        'organization_id',
        'datev_booking_batch_id',
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
            'datev_booking_batch_id' => $this->nullableInt($this->getAttribute('datev_booking_batch_id')),
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
