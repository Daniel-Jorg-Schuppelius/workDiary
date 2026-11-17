<?php
/*
 * Created on   : Thu Jul 16 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : AiProviderConnection.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Models\Ai;

use App\Enums\Ai\{AiConnectionStatus, AiFamily, AiProviderType, AiVerb};
use App\Models\Attachments\Attachment;
use App\Models\Concerns\{Auditable, BelongsToOrganization, HasAttachments, HasConnectionHealth, HasSqid};
use App\Models\Platform\User;
use Illuminate\Database\Eloquent\Factories\{Factory, HasFactory};
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * KI-Provider-Verbindung (Feature 025, MVP-399): eine Organisation kann
 * MEHRERE Verbindungen je Familie führen (auch mehrere desselben Typs).
 * Der API-Schlüssel ist verschlüsselt und `hidden` — er erscheint nie in
 * Serialisierung, Audit oder Logs. `is_local` entscheidet zusammen mit
 * Capability-Sensibilität und Branchenprofil, ob die Verbindung geroutet
 * werden darf; bei Typen ohne freie Einstufung erzwingt das Model den
 * Typ-Default (nur `openai_compatible` darf der Admin bewusst einstufen).
 *
 * Datenschutzfreigabe (MVP-1672): AVV liegt vor (`has_dpa`, Dokument als
 * Anhang {@see DPA_META}), Verarbeitung in der EU, keine Trainingsnutzung,
 * geprüft am/von — ausgewertet in {@see \App\Services\Ai\AiConnectionPrivacy}.
 *
 * @property int $id
 * @property int|null $organization_id
 * @property AiFamily $family
 * @property AiProviderType $provider
 * @property string $name
 * @property string|null $base_url
 * @property string|null $api_key
 * @property string|null $model
 * @property array<string, mixed>|null $options
 * @property bool $is_local
 * @property AiConnectionStatus $status
 * @property Carbon|null $preflight_at
 * @property string|null $last_error
 * @property Carbon|null $last_error_at
 * @property int $consecutive_failures
 * @property Carbon|null $disabled_at
 * @property bool $has_dpa
 * @property bool $is_eu_processing
 * @property bool $is_training_excluded
 * @property Carbon|null $privacy_reviewed_at
 * @property int|null $privacy_reviewed_by
 */
class AiProviderConnection extends Model {
    use Auditable;
    use BelongsToOrganization;
    use HasAttachments;
    use HasConnectionHealth;

    /** @use HasFactory<Factory<static>> */
    use HasFactory;

    use HasSqid;

    /** Ablage des AVV-Dokuments als Anhang (`attachments.meta_type`). */
    public const string DPA_META = 'ai_dpa';

    protected $fillable = [
        'organization_id',
        'family',
        'provider',
        'name',
        'base_url',
        'api_key',
        'model',
        'options',
        'is_local',
        'status',
        'preflight_at',
        'created_by_user_id',
    ];

    /** API-Schlüssel nie in Arrays/JSON/Audit/Logs. */
    protected $hidden = [
        'api_key',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'family' => AiFamily::class,
        'provider' => AiProviderType::class,
        'status' => AiConnectionStatus::class,
        'api_key' => 'encrypted',
        'options' => 'array',
        'is_local' => 'boolean',
        'preflight_at' => 'datetime',
        'last_error_at' => 'datetime',
        'disabled_at' => 'datetime',
        'has_dpa' => 'boolean',
        'is_eu_processing' => 'boolean',
        'is_training_excluded' => 'boolean',
        'privacy_reviewed_at' => 'datetime',
    ];

    protected static function booted(): void {
        static::saving(function (self $connection): void {
            // Lokalität ist nur beim generischen Adapter eine bewusste
            // Admin-Entscheidung; alle anderen Typen erzwingen den Default
            // (Sensibilitäts-Gate darf nicht per Datenpflege kippen).
            if (! $connection->provider->allowsLocalOverride()) {
                $connection->is_local = $connection->provider->isLocalByDefault();
            }
        });
    }

    /** Betriebsbereit nur mit aktivem Status und ohne Auto-Disable. */
    public function isRunnable(): bool {
        return $this->status->isRunnable() && ! $this->isConnectionFailing();
    }

    public function isCloud(): bool {
        return ! $this->is_local;
    }

    /** Bedient die Verbindung das Verb (Familien-Zuordnung)? */
    public function supportsVerb(AiVerb $verb): bool {
        return in_array($this->family, $verb->allowedFamilies(), true);
    }

    /** @return BelongsTo<User, $this> */
    public function creator(): BelongsTo {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    /**
     * Wer die Datenschutzangaben zuletzt geprüft und freigegeben hat (MVP-1672).
     *
     * @return BelongsTo<User, $this>
     */
    public function privacyReviewer(): BelongsTo {
        return $this->belongsTo(User::class, 'privacy_reviewed_by');
    }

    /** Jüngstes AVV-Dokument der Verbindung. */
    public function dpaDocument(): ?Attachment {
        return $this->attachmentByMeta(self::DPA_META);
    }

    /**
     * Freigabe und Widerruf heißen im Audit-Protokoll so, nicht „geändert“.
     *
     * @param  array<string, mixed>  $changes
     */
    protected function resolveAuditEvent(string $event, array $changes): string {
        if ($event === 'updated' && array_key_exists('privacy_reviewed_at', $changes)) {
            return $changes['privacy_reviewed_at'] === null ? 'ai.connection_approval_revoked' : 'ai.connection_approved';
        }

        return $event;
    }
}
