<?php
/*
 * Created on   : Tue Aug 25 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : SafetyInstructionParticipant.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Models\Safety;

use App\Casts\IpAddressCast;
use App\Enums\Safety\InstructionSignatureMethod;
use App\Models\Concerns\{Auditable, BelongsToOrganization, HasSqid};
use App\Models\User;
use CommonToolkit\ValueObjects\IpAddress;
use Database\Factories\Safety\SafetyInstructionParticipantFactory;
use Illuminate\Database\Eloquent\{Builder, Model};
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Teilnahme-Nachweis einer Unterweisung (Feature 132) — das
 * ProtocolSignature-Muster in schlank: signer_name + signed_at + Methode
 * (Bestätigungs-Klick mit IP oder gezeichnete Unterschrift als Bild) und
 * ein Inhalts-Hash über Unterweisung/Person/Zeitpunkt. next_due_on ist die
 * Wiederholungsfälligkeit DIESER Person (Datum + Intervall der Unterweisung).
 * Signierte Zeilen sind Nachweis und werden nie entfernt.
 *
 * @property int $id
 * @property int $organization_id
 * @property int $safety_instruction_id
 * @property int $user_id
 * @property string|null $signer_name
 * @property Carbon|null $signed_at
 * @property InstructionSignatureMethod|null $method
 * @property string|null $signature_image_path
 * @property IpAddress|null $ip
 * @property string|null $hash
 * @property Carbon|null $next_due_on
 */
class SafetyInstructionParticipant extends Model {
    use Auditable;

    use BelongsToOrganization;
    /** @use HasFactory<SafetyInstructionParticipantFactory> */
    use HasFactory;
    use HasSqid;

    protected $fillable = [
        'organization_id',
        'safety_instruction_id',
        'user_id',
        'signer_name',
        'signed_at',
        'method',
        'signature_image_path',
        'ip',
        'hash',
        'next_due_on',
    ];

    protected $casts = [
        'signed_at' => 'datetime',
        'method' => InstructionSignatureMethod::class,
        'ip' => IpAddressCast::class,
        'next_due_on' => 'date',
    ];

    public function isSigned(): bool {
        return $this->signed_at !== null;
    }

    public function isDueOverdue(): bool {
        return $this->next_due_on !== null && $this->next_due_on->isPast() && ! $this->next_due_on->isToday();
    }

    /** @return BelongsTo<SafetyInstruction, $this> */
    public function instruction(): BelongsTo {
        return $this->belongsTo(SafetyInstruction::class, 'safety_instruction_id');
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo {
        return $this->belongsTo(User::class);
    }

    /**
     * Signierte Nachweise.
     *
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeSigned(Builder $query): Builder {
        return $query->whereNotNull('signed_at');
    }
}
