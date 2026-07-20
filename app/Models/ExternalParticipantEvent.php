<?php
/*
 * Created on   : Sat Jun 14 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ExternalParticipantEvent.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Models;

use App\Models\Concerns\AppendOnly;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Append-only Nachweis aller externen Aktionen (Feature 033): Zugriff,
 * Kommentar, Upload, Bestätigung. Akteur ist der externe Beteiligte (über die
 * Relation), nicht ein interner User — daher bewusst getrennt vom AuditLog.
 *
 * Kind-Tabelle des tenant-gebundenen ExternalParticipant; die Mandantengrenze
 * wird transitiv über external_participants.organization_id durchgesetzt
 * (Allow-List-Eintrag im TenantTraitCoverageTest). Append-only: nur
 * created_at, kein updated_at, keine Updates/Deletes im Anwendungspfad.
 *
 * @property int $id
 * @property int $external_participant_id
 * @property string $event
 * @property array<string, mixed>|null $payload
 * @property string|null $ip
 * @property string|null $user_agent
 * @property Carbon|null $created_at
 */
class ExternalParticipantEvent extends Model {
    // Append-only jetzt technisch erzwungen statt nur dokumentiert (Vollaudit 2026-07, M52).
    use AppendOnly;

    /** Append-only-Lebenszyklus: nur created_at (kein updated_at). */
    public const UPDATED_AT = null;

    protected $fillable = [
        'external_participant_id',
        'event',
        'payload',
        'ip',
        'user_agent',
        'created_at',
    ];

    protected $casts = [
        'payload' => 'array',
        'created_at' => 'datetime',
    ];

    /** @return BelongsTo<ExternalParticipant, $this> */
    public function participant(): BelongsTo {
        return $this->belongsTo(ExternalParticipant::class, 'external_participant_id');
    }
}
