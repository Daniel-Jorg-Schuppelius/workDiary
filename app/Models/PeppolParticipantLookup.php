<?php
/*
 * Created on   : Wed Aug 26 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : PeppolParticipantLookup.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Zwischenspeicher der Peppol-Teilnehmerauflösung (Feature 066, MVP-734).
 *
 * SML (DNS/NAPTR) und SMP (HTTP) beantworten zwei Fragen: Ist der Empfänger
 * überhaupt in Peppol registriert, und nimmt er das gewünschte Dokumentformat
 * an? Beides ändert sich selten, kostet aber je Abfrage eine DNS- und eine
 * HTTP-Runde — deshalb wird das Ergebnis mit Ablaufzeit festgehalten statt bei
 * jedem Versand neu geholt. Der Datensatz ist bewusst **kein Stammdatum**:
 * `checked_at` sagt, wie alt die Auskunft ist, und ein Ablauf verwirft sie.
 *
 * @property int $id
 * @property int $organization_id
 * @property string $participant
 * @property bool $registered
 * @property string|null $smp_base_url
 * @property list<string>|null $document_types
 * @property string|null $message
 * @property Carbon $checked_at
 */
class PeppolParticipantLookup extends Model {
    use BelongsToOrganization;

    protected $fillable = [
        'organization_id', 'participant', 'registered', 'smp_base_url',
        'document_types', 'message', 'checked_at',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'registered' => 'boolean',
        'document_types' => 'array',
        'checked_at' => 'datetime',
    ];

    /** Auskunft älter als die konfigurierte Gültigkeit ⇒ erneut auflösen. */
    public function isStale(int $ttlHours): bool {
        if ($ttlHours <= 0) {
            return true;
        }

        return $this->checked_at->addHours($ttlHours)->isPast();
    }

    /** Nimmt der Teilnehmer diesen (kanonischen) Dokumenttyp an? */
    public function supports(string $documentTypeId): bool {
        return in_array($documentTypeId, $this->document_types ?? [], true);
    }
}
