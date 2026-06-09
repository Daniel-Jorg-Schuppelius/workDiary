<?php
/*
 * Created on   : Mon Jun 08 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : CaseEvent.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Models\Whistleblowing;

use App\Models\Concerns\{HashChainable, HashChained};
use Illuminate\Database\Eloquent\Model;

/**
 * Spezialisiertes, append-only Ereignisprotokoll fuer Hinweisgeberfaelle
 * (Abschnitt 9.6 / 12). Bewusst NICHT der allgemeine Auditable/AuditLog-Pfad
 * (der speichert IP/User-Agent). Hier: minimierte Metadaten ohne Meldeinhalte,
 * keine Reporter-IP/-UA, abgesichert ueber die {@see HashChained}-Hash-Kette
 * (registriert in config('audit.chains'), pruefbar via `audit:verify`).
 *
 * Ohne BelongsToOrganization (wie OrganizationAuditLog): die Kette muss ohne
 * Scope ueber alle Zeilen verifizierbar sein und Eintraege ueberdauern.
 *
 * @property string|null $prev_hash
 * @property string|null $hash
 *
 * @phpstan-consistent-constructor
 */
class CaseEvent extends Model implements HashChainable {
    use HashChained;

    protected $table = 'whistleblowing_case_events';

    public $timestamps = false;

    protected $fillable = [
        'organization_id',
        'case_id',
        'actor_type',
        'actor_user_id',
        'event',
        'metadata',
        'created_at',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'metadata' => 'array',
        'created_at' => 'datetime',
    ];

    /**
     * In den Hash eingehende Nutzdaten (feste Reihenfolge, IDs null-erhaltend
     * normalisiert – treiberunabhaengig).
     *
     * @return array<string, mixed>
     */
    public function hashPayload(): array {
        return [
            'organization_id' => $this->nullableInt($this->getAttribute('organization_id')),
            'case_id' => $this->nullableInt($this->getAttribute('case_id')),
            'actor_type' => $this->getAttribute('actor_type'),
            'actor_user_id' => $this->nullableInt($this->getAttribute('actor_user_id')),
            'event' => $this->getAttribute('event'),
            'metadata' => $this->getAttribute('metadata'),
            'created_at' => $this->hashCreatedAt(),
        ];
    }

    private function nullableInt(mixed $value): ?int {
        return $value === null ? null : (int) $value;
    }
}
