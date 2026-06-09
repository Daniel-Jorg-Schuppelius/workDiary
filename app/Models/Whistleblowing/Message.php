<?php
/*
 * Created on   : Mon Jun 08 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : Message.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Models\Whistleblowing;

use App\Enums\Whistleblowing\{MessageAuthorType, MessageVisibility};
use App\Models\Concerns\BelongsToOrganization;
use App\Models\Whistleblowing\Casts\CaseEncrypted;
use App\Models\Whistleblowing\Concerns\ProvidesCaseDek;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Nachricht im gemeinsamen Strom von Postfach und interner Kommunikation.
 * `visibility=internal` = interne Notiz. Nachrichten werden nicht editiert
 * (Korrektur = neue Nachricht). Body ist mit dem Fall-DEK verschluesselt.
 *
 * @property string|null $body_ciphertext Klartext beim Lesen/Setzen
 */
class Message extends Model implements ProvidesCaseDek {
    use BelongsToOrganization;

    protected $table = 'whistleblowing_messages';

    public const UPDATED_AT = null; // Nachrichten sind unveraenderlich

    protected $fillable = [
        'organization_id',
        'case_id',
        'author_type',
        'author_user_id',
        'visibility',
        'body_ciphertext',
        'sent_at',
        'read_by_reporter_at',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'author_type' => MessageAuthorType::class,
        'visibility' => MessageVisibility::class,
        'body_ciphertext' => CaseEncrypted::class,
        'sent_at' => 'datetime',
        'read_by_reporter_at' => 'datetime',
    ];

    public function caseDek(): ?string {
        return $this->case?->caseDek();
    }

    /** @return BelongsTo<WhistleblowingCase, $this> */
    public function case(): BelongsTo {
        return $this->belongsTo(WhistleblowingCase::class, 'case_id');
    }
}
