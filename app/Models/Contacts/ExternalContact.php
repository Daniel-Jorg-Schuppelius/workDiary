<?php
/*
 * Created on   : Mon Jul 07 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ExternalContact.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Models;

use App\Enums\ExternalParticipant\ExternalParty;
use App\Models\Concerns\{BelongsToOrganization, HasSqid};
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Wiederverwendbares externes Kontakt-/Rollenprofil (Feature 033, Rang 30):
 * Stammdaten eines wiederkehrenden externen Beteiligten. Beim Einladen kann ein
 * bestehendes Profil gewählt werden (füllt Name/E-Mail/Rolle/Art vor); die
 * Einladung selbst bleibt der eigentliche, befristete Zugang mit eigenem Token.
 *
 * @property int $id
 * @property int $organization_id
 * @property string $name
 * @property string|null $email
 * @property string|null $role
 * @property ExternalParty $party
 * @property string|null $notes
 */
class ExternalContact extends Model {
    use BelongsToOrganization;

    /** @use HasFactory<\Database\Factories\ExternalContactFactory> */
    use HasFactory;

    use HasSqid;

    protected $fillable = [
        'organization_id',
        'name',
        'email',
        'role',
        'party',
        'notes',
    ];

    protected $casts = [
        'party' => ExternalParty::class,
    ];

    /** @return HasMany<ExternalParticipant, $this> Aus diesem Profil erzeugte Einladungen. */
    public function participants(): HasMany {
        return $this->hasMany(ExternalParticipant::class);
    }
}
