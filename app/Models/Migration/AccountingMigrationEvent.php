<?php
/*
 * Created on   : Wed Aug 19 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : AccountingMigrationEvent.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Models\Migration;

use App\Models\Concerns\{HashChainable, HashChained};
use Illuminate\Database\Eloquent\Model;

/**
 * Append-only Ereignisprotokoll eines Buchhaltungswechsels (MVP-653):
 * Start, Freigaben, Konfliktentscheidungen, Umschaltung, Abbruch und
 * Abschluss werden revisionssicher über die {@see HashChained}-Kette
 * protokolliert (registriert in config('audit.chains'), prüfbar via
 * `audit:verify`).
 *
 * Ohne BelongsToOrganization und ohne Fremdschlüssel (Muster
 * {@see \App\Models\Finance\BillingTransferEvent}): die Kette muss scope-frei
 * verifizierbar sein und Einträge überdauern die Löschung von Lauf und
 * Organisation — ein Cascade würde die Kette zerreißen.
 *
 * @property string|null $prev_hash
 * @property string|null $hash
 *
 * @phpstan-consistent-constructor
 */
class AccountingMigrationEvent extends Model implements HashChainable {
    use HashChained;

    protected $table = 'accounting_migration_events';

    public $timestamps = false;

    protected $fillable = [
        'organization_id',
        'accounting_migration_run_id',
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
     * In den Hash eingehende Nutzdaten (feste Reihenfolge, IDs
     * null-erhaltend normalisiert — treiberunabhängig).
     *
     * @return array<string, mixed>
     */
    public function hashPayload(): array {
        return [
            'organization_id' => $this->nullableInt($this->getAttribute('organization_id')),
            'accounting_migration_run_id' => $this->nullableInt($this->getAttribute('accounting_migration_run_id')),
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
