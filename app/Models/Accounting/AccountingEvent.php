<?php
/*
 * Created on   : Fri Aug 21 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : AccountingEvent.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Models\Accounting;

use App\Models\Concerns\{HashChainable, HashChained};
use Illuminate\Database\Eloquent\Model;

/**
 * Revisionssicherer Nachweis des Buchungskerns (Feature 125, MVP-672):
 * Festschreibung, Storno, Eröffnungsbuchung und später Periodenabschluss.
 *
 * Append-only und über eine SHA-256-Kette verbunden (Muster
 * {@see \App\Models\Finance\DatevBookingEvent}). Bewusst ohne
 * `BelongsToOrganization`: Die Kette muss auch ohne Mandantenkontext
 * vollständig prüfbar bleiben (`audit:verify`).
 *
 * @phpstan-consistent-constructor
 */
class AccountingEvent extends Model implements HashChainable {
    use HashChained;

    public const UPDATED_AT = null;

    protected $fillable = [
        'organization_id',
        'accounting_entry_id',
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
     * Nutzdaten in fester Reihenfolge. Rohwerte über getAttribute — ein Cast
     * würde die Kette beim ersten Formatwechsel unprüfbar machen.
     *
     * @return array<string, mixed>
     */
    public function hashPayload(): array {
        return [
            'organization_id' => $this->nullableInt($this->getAttribute('organization_id')),
            'accounting_entry_id' => $this->nullableInt($this->getAttribute('accounting_entry_id')),
            'event' => $this->getAttribute('event'),
            'actor_user_id' => $this->nullableInt($this->getAttribute('actor_user_id')),
            'payload' => $this->getAttributes()['payload'] ?? null,
            'created_at' => $this->hashCreatedAt(),
        ];
    }

    private function nullableInt(mixed $value): ?int {
        return $value === null ? null : (int) $value;
    }
}
