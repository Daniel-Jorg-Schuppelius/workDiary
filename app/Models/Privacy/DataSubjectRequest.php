<?php
/*
 * Created on   : Tue Jun 09 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : DataSubjectRequest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Models\Privacy;

use App\Enums\Privacy\{DataSubjectRequestStatus, DataSubjectRequestType};
use App\Models\Concerns\{BelongsToOrganization, HasSqid};
use App\Models\Privacy\Casts\RecordEncrypted;
use App\Models\Privacy\Concerns\ProvidesRecordDek;
use App\Models\User;
use App\Services\Privacy\DataProtectionCryptoService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\{BelongsTo, HasMany};

/**
 * Betroffenenanfrage (DSGVO Art. 15–21). Identitaet/Anliegen sind per-Fall
 * verschluesselt (DEK in `dek_wrapped`, gewrappt mit DATAPROTECTION_KEY);
 * Crypto-Shredding macht den Fall nach Aufbewahrung unwiederbringlich. Der
 * pruefbare Verlauf liegt in der {@see RequestEvent}-Hash-Kette.
 *
 * @property string|null $subject_ciphertext  Klartext beim Lesen/Setzen
 * @property string|null $content_ciphertext
 * @property string|null $decision_note_ciphertext
 */
class DataSubjectRequest extends Model implements ProvidesRecordDek {
    use BelongsToOrganization;
    use HasSqid;

    protected $table = 'privacy_data_subject_requests';

    protected $fillable = [
        'organization_id',
        'request_number',
        'type',
        'status',
        'channel',
        'identity_verified_at',
        'assigned_user_id',
        'received_at',
        'deadline_at',
        'subject_ciphertext',
        'content_ciphertext',
        'decision_note_ciphertext',
        'dek_wrapped',
        'decision',
        'decided_at',
        'closed_at',
        'created_by',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'type' => DataSubjectRequestType::class,
        'status' => DataSubjectRequestStatus::class,
        'subject_ciphertext' => RecordEncrypted::class,
        'content_ciphertext' => RecordEncrypted::class,
        'decision_note_ciphertext' => RecordEncrypted::class,
        'identity_verified_at' => 'datetime',
        'received_at' => 'datetime',
        'deadline_at' => 'datetime',
        'decided_at' => 'datetime',
        'closed_at' => 'datetime',
    ];

    private ?string $plainDek = null;

    // ── DEK-Lifecycle ───────────────────────────────────────────────────────

    /** Erzeugt einen frischen DEK und legt ihn gewrappt in dek_wrapped ab. */
    public function initializeDek(): void {
        $crypto = app(DataProtectionCryptoService::class);
        $this->plainDek = $crypto->generateDek();
        $this->setAttribute('dek_wrapped', $crypto->wrapDek($this->plainDek));
    }

    public function recordDek(): ?string {
        if ($this->plainDek !== null) {
            return $this->plainDek;
        }
        $wrapped = $this->getAttribute('dek_wrapped');
        if (empty($wrapped)) {
            return null;
        }

        return $this->plainDek = app(DataProtectionCryptoService::class)->unwrapDek((string) $wrapped);
    }

    /** Crypto-Shredding: DEK vernichten → alle verschluesselten Inhalte verloren. */
    public function shredDek(): void {
        $this->plainDek = null;
        $this->forceFill(['dek_wrapped' => null])->save();
    }

    // ── Beziehungen / Helper ─────────────────────────────────────────────────

    /** @return BelongsTo<User, $this> */
    public function assignedUser(): BelongsTo {
        return $this->belongsTo(User::class, 'assigned_user_id');
    }

    /** @return HasMany<RequestEvent, $this> */
    public function events(): HasMany {
        return $this->hasMany(RequestEvent::class, 'request_id')->orderBy('id');
    }

    public function isOverdue(): bool {
        $deadline = $this->getAttribute('deadline_at');

        return $this->status->isOpen()
            && $deadline !== null
            && $deadline->isPast();
    }
}
