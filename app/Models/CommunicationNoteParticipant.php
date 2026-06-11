<?php
/*
 * Created on   : Wed Jun 10 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : CommunicationNoteParticipant.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Models;

use App\Enums\Communication\ParticipantParty;
use Database\Factories\CommunicationNoteParticipantFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Beteiligte einer Kommunikationsnotiz (intern, Kunde, Dritte).
 * Mandantengrenze transitiv über die tenant-gebundene CommunicationNote.
 *
 * @property int $id
 * @property int $communication_note_id
 * @property int|null $user_id
 * @property int|null $customer_contact_id
 * @property string $name
 * @property string|null $role
 * @property ParticipantParty $party
 */
class CommunicationNoteParticipant extends Model {
    /** @use HasFactory<CommunicationNoteParticipantFactory> */
    use HasFactory;

    protected $fillable = [
        'communication_note_id',
        'user_id',
        'customer_contact_id',
        'name',
        'role',
        'party',
    ];

    protected $casts = [
        'party' => ParticipantParty::class,
    ];

    /** @return BelongsTo<CommunicationNote, $this> */
    public function note(): BelongsTo {
        return $this->belongsTo(CommunicationNote::class, 'communication_note_id');
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo {
        return $this->belongsTo(User::class);
    }
}
