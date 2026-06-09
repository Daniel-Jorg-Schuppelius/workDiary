<?php
/*
 * Created on   : Mon Jun 08 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : WhistleblowingCase.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Models\Whistleblowing;

use App\Enums\Whistleblowing\{CaseCategory, CasePriority, CaseStatus, ReporterMode};
use App\Models\Concerns\BelongsToOrganization;
use App\Models\User;
use App\Models\Whistleblowing\Casts\CaseEncrypted;
use App\Models\Whistleblowing\Concerns\ProvidesCaseDek;
use App\Services\Whistleblowing\WhistleblowingCryptoService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\{BelongsToMany, HasMany};
use Illuminate\Support\Str;

/**
 * Ein Hinweisgeberfall. Inhalte (Betreff, Beschreibung, Kontakt) liegen
 * fall-bezogen verschluesselt vor; der DEK wird vom Modul-KEK gewrappt in
 * `dek_wrapped` gehalten. Es werden bewusst KEINE Reporter-IP, -User-Agent oder
 * user_id gespeichert (Abschnitt 6 / 9.2 / 25).
 *
 * Die Klar-Inhalte werden ueber die *_ciphertext-Attribute gelesen/geschrieben;
 * der {@see CaseEncrypted}-Cast ver-/entschluesselt transparent mit dem Fall-DEK.
 *
 * @property string|null $subject_ciphertext   Klartext beim Lesen/Setzen
 * @property string|null $description_ciphertext
 * @property string|null $contact_ciphertext
 */
class WhistleblowingCase extends Model implements ProvidesCaseDek {
    use BelongsToOrganization;

    protected $table = 'whistleblowing_cases';

    /** Laufzeit-DEK (nie persistiert). */
    private ?string $plainDek = null;

    /** Route-Binding ueber die zufaellige public_id, nie ueber die sequentielle id. */
    public function getRouteKeyName(): string {
        return 'public_id';
    }

    /**
     * Bewusst eng: Inhalte/Meta, die der Report-Service setzt. Hashes, DEK,
     * public_id, Fristen und Status werden NICHT massenzuweisbar gesetzt
     * (Mass-Assignment-Schutz, Abschnitt 9/20).
     */
    protected $fillable = [
        'organization_id',
        'reporter_mode',
        'category',
        'priority',
        'occurred_from',
        'occurred_to',
        'subject_ciphertext',
        'description_ciphertext',
        'contact_ciphertext',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'reporter_mode' => ReporterMode::class,
        'category' => CaseCategory::class,
        'status' => CaseStatus::class,
        'priority' => CasePriority::class,
        'subject_ciphertext' => CaseEncrypted::class,
        'description_ciphertext' => CaseEncrypted::class,
        'contact_ciphertext' => CaseEncrypted::class,
        'occurred_from' => 'date',
        'occurred_to' => 'date',
        'acknowledgement_due_at' => 'datetime',
        'feedback_due_at' => 'datetime',
        'acknowledged_at' => 'datetime',
        'feedback_sent_at' => 'datetime',
        'closed_at' => 'datetime',
        'retention_due_at' => 'datetime',
        'legal_hold_at' => 'datetime',
    ];

    protected static function booted(): void {
        static::creating(function (WhistleblowingCase $case): void {
            if (empty($case->getAttribute('public_id'))) {
                $case->setAttribute('public_id', (string) Str::uuid()); // UUIDv4, nicht zeit-geordnet
            }
            if ($case->getAttribute('status') === null) {
                $case->setAttribute('status', CaseStatus::Submitted->value);
            }
            if ($case->getAttribute('priority') === null) {
                $case->setAttribute('priority', CasePriority::Normal->value);
            }
        });
    }

    // ── DEK-Lifecycle ───────────────────────────────────────────────────────

    /** Erzeugt einen frischen DEK und legt ihn gewrappt in dek_wrapped ab. */
    public function initializeDek(): void {
        $crypto = app(WhistleblowingCryptoService::class);
        $this->plainDek = $crypto->generateDek();
        $this->setAttribute('dek_wrapped', $crypto->wrapDek($this->plainDek));
    }

    public function caseDek(): ?string {
        if ($this->plainDek !== null) {
            return $this->plainDek;
        }
        $wrapped = $this->getAttribute('dek_wrapped');
        if (empty($wrapped)) {
            return null;
        }

        return $this->plainDek = app(WhistleblowingCryptoService::class)->unwrapDek((string) $wrapped);
    }

    /** Crypto-Shredding: DEK vernichten → alle Inhalte unwiederbringlich. */
    public function shredDek(): void {
        $this->plainDek = null;
        $this->forceFill(['dek_wrapped' => null])->save();
    }

    // ── Beziehungen ─────────────────────────────────────────────────────────

    /** @return HasMany<CaseAssignment, $this> */
    public function assignments(): HasMany {
        return $this->hasMany(CaseAssignment::class, 'case_id');
    }

    /** @return HasMany<Message, $this> */
    public function messages(): HasMany {
        return $this->hasMany(Message::class, 'case_id');
    }

    /** @return HasMany<Attachment, $this> */
    public function attachments(): HasMany {
        return $this->hasMany(Attachment::class, 'case_id');
    }

    /**
     * Aktiv (nicht widerrufen) zugewiesene Bearbeiter.
     *
     * @return BelongsToMany<User, $this>
     */
    public function handlers(): BelongsToMany {
        return $this->belongsToMany(User::class, 'whistleblowing_case_assignments', 'case_id', 'user_id')
            ->wherePivotNull('revoked_at');
    }

    /** Ist der Benutzer aktiv diesem Fall zugewiesen? */
    public function isAssigned(User $user): bool {
        return $this->assignments()
            ->where('user_id', $user->getKey())
            ->whereNull('revoked_at')
            ->exists();
    }

    /** @return HasMany<CaseConflict, $this> */
    public function conflicts(): HasMany {
        return $this->hasMany(CaseConflict::class, 'case_id');
    }

    /** @return HasMany<EmergencyGrant, $this> */
    public function emergencyGrants(): HasMany {
        return $this->hasMany(EmergencyGrant::class, 'case_id');
    }

    /** @return HasMany<CaseSubject, $this> */
    public function subjects(): HasMany {
        return $this->hasMany(CaseSubject::class, 'case_id');
    }

    /** Hat der Benutzer einen Interessenkonflikt fuer diesen Fall erklaert? */
    public function hasConflictFor(User $user): bool {
        return $this->conflicts()->where('user_id', $user->getKey())->exists();
    }

    /** Ist der Benutzer als Betroffener/Beschuldigter dieses Falls markiert? */
    public function isSubjectFor(User $user): bool {
        return $this->subjects()->where('user_id', $user->getKey())->exists();
    }

    /** Gesperrt = Interessenkonflikt ODER benannter Betroffener. */
    public function isBlockedFor(User $user): bool {
        return $this->hasConflictFor($user) || $this->isSubjectFor($user);
    }

    /** Besitzt der Benutzer eine aktive (nicht abgelaufene) Notfallfreigabe? */
    public function hasActiveEmergencyGrantFor(User $user): bool {
        return $this->emergencyGrants()->active()->where('user_id', $user->getKey())->exists();
    }
}
