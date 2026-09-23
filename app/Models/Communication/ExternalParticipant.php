<?php
/*
 * Created on   : Sat Jun 14 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ExternalParticipant.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Models;

use App\Enums\ExternalParticipant\{ExternalAbility, ExternalParty};
use App\Models\Concerns\{BelongsToOrganization, HasSqid};
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\{BelongsTo, HasMany, MorphTo};
use Illuminate\Support\Carbon;

/**
 * Kontextbezogene externe Einladung (Feature 033): Subunternehmer, Prüfer
 * oder Sachverständiger wird befristet, login-frei und mit begrenzten Rechten
 * an ein Subject (DiaryEntry|Protocol|Document) beteiligt.
 *
 * Der Klartext-Token wird NICHT gespeichert; persistiert wird nur der
 * SHA-256-Hash (Muster {@see ProtocolSignatureToken} /
 * {@see \App\Models\Isms\IsmsAuditPackageToken}). Der Klartext wird genau
 * EINMAL bei der Einladung angezeigt.
 *
 * Org-gebunden via BelongsToOrganization: die interne Verwaltung läuft immer
 * org-gescopt; der öffentliche Zugriff löst den Token im Public-Controller
 * scope-frei (withoutGlobalScopes) über den Hash auf.
 *
 * Append-only-Lebenszyklus: nur created_at (kein updated_at) — Statuswechsel
 * (accepted/last_access/revoked) werden über forceFill/save gesetzt.
 *
 * @property int $id
 * @property int $organization_id
 * @property string $subject_type
 * @property int $subject_id
 * @property string $name
 * @property string|null $email
 * @property string|null $role
 * @property ExternalParty $party
 * @property string $token_hash
 * @property list<string> $abilities
 * @property Carbon $expires_at
 * @property int|null $invited_by_user_id
 * @property Carbon|null $accepted_at
 * @property Carbon|null $last_access_at
 * @property Carbon|null $revoked_at
 * @property Carbon|null $created_at
 */
class ExternalParticipant extends Model {
    use BelongsToOrganization;

    /** @use HasFactory<\Database\Factories\ExternalParticipantFactory> */
    use HasFactory;

    use HasSqid;

    /** Append-only-Lebenszyklus: nur created_at (kein updated_at). */
    public const UPDATED_AT = null;

    protected $fillable = [
        'organization_id',
        'subject_type',
        'subject_id',
        'external_contact_id',
        'name',
        'email',
        'role',
        'party',
        'token_hash',
        'abilities',
        'expires_at',
        'invited_by_user_id',
        'accepted_at',
        'last_access_at',
        'revoked_at',
        'created_at',
    ];

    protected $casts = [
        'party' => ExternalParty::class,
        'abilities' => 'array',
        'expires_at' => 'datetime',
        'accepted_at' => 'datetime',
        'last_access_at' => 'datetime',
        'revoked_at' => 'datetime',
        'created_at' => 'datetime',
    ];

    /** @return MorphTo<Model, $this> */
    public function subject(): MorphTo {
        return $this->morphTo();
    }

    /** @return BelongsTo<User, $this> */
    public function invitedBy(): BelongsTo {
        return $this->belongsTo(User::class, 'invited_by_user_id');
    }

    /** @return BelongsTo<ExternalContact, $this> Wiederverwendbares Kontaktprofil (Rang 30), sofern gewählt. */
    public function externalContact(): BelongsTo {
        return $this->belongsTo(ExternalContact::class);
    }

    /** @return HasMany<ExternalParticipantEvent, $this> */
    public function events(): HasMany {
        return $this->hasMany(ExternalParticipantEvent::class)->latest('id');
    }

    /** Nicht widerrufen und nicht abgelaufen? */
    public function isUsable(): bool {
        return $this->revoked_at === null && $this->expires_at->isFuture();
    }

    /**
     * Prüft strikt, ob der Externe eine bestimmte Aktion ausführen darf.
     * `View` ist für jeden gültigen Token implizit erlaubt; alle anderen
     * Aktionen müssen explizit in abilities stehen.
     */
    public function can(ExternalAbility $ability): bool {
        if ($ability === ExternalAbility::View) {
            return true;
        }

        return in_array($ability->value, (array) $this->abilities, true);
    }

    /** Lesbarer Statuscode für die Verwaltungsliste. */
    public function status(): string {
        return match (true) {
            $this->revoked_at !== null => 'revoked',
            $this->expires_at->isPast() => 'expired',
            $this->accepted_at !== null => 'accessed',
            default => 'invited',
        };
    }
}
