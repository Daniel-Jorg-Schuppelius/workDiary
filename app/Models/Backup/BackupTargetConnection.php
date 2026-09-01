<?php
/*
 * Created on   : Tue Jul 14 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : BackupTargetConnection.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Models\Backup;

use App\Enums\Backup\{BackupProvider, BackupTargetStatus};
use App\Models\Concerns\{Auditable, HasConnectionHealth, HasSqid};
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\{Factory, HasFactory};
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\{BelongsTo, HasMany};
use Illuminate\Support\Carbon;

/**
 * Backupziel-Verbindung (Feature 017 Phase 32, MVP-361): OAuth-gebundenes
 * Provider-Konto für verschlüsselte Offsite-Backups. SYSTEMWEIT — bewusst
 * ohne organization_id, Verwaltung ausschließlich Plattform-Admin
 * ({@see \App\Policies\Backup\BackupTargetConnectionPolicy}); strikt getrennt
 * von den Dokumentimport-Verbindungen (eigene Scopes, eigene Tokens).
 *
 * @property int $id
 * @property BackupProvider $provider
 * @property string $name
 * @property string|null $external_account_id
 * @property string|null $external_account_label
 * @property string|null $server_url
 * @property string|null $username
 * @property string|null $access_token
 * @property string|null $refresh_token
 * @property Carbon|null $token_expires_at
 * @property array<int, string>|null $granted_scopes
 * @property string|null $root_folder_ref
 * @property array<string, mixed>|null $options
 * @property int|null $quota_total
 * @property int|null $quota_used
 * @property Carbon|null $quota_checked_at
 * @property BackupTargetStatus $status
 * @property int|null $created_by_user_id
 * @property-read \Illuminate\Database\Eloquent\Collection<int, BackupGeneration> $generations
 */
class BackupTargetConnection extends Model {
    use Auditable;
    use HasConnectionHealth;

    /** @use HasFactory<Factory<static>> */
    use HasFactory;

    use HasSqid;

    protected $fillable = [
        'provider',
        'name',
        'external_account_id',
        'external_account_label',
        // Nextcloud (MVP-383): Server-URL + Nutzer; App-Passwort in access_token.
        'server_url',
        'username',
        'access_token',
        'refresh_token',
        'token_expires_at',
        'granted_scopes',
        'root_folder_ref',
        // Providereigene Einstellungen ohne Geheimnischarakter (MVP-726):
        // S3 legt hier Region und Path-Style ab.
        'options',
        'quota_total',
        'quota_used',
        'quota_checked_at',
        'status',
        'created_by_user_id',
    ];

    /** Tokens nie in Arrays/JSON/Logs (Supportredaktion). */
    protected $hidden = [
        'access_token',
        'refresh_token',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'provider' => BackupProvider::class,
        'status' => BackupTargetStatus::class,
        'access_token' => 'encrypted',
        'refresh_token' => 'encrypted',
        'token_expires_at' => 'datetime',
        'granted_scopes' => 'array',
        'options' => 'array',
        'quota_total' => 'integer',
        'quota_used' => 'integer',
        'quota_checked_at' => 'datetime',
    ];

    /** @return HasMany<BackupGeneration, $this> */
    public function generations(): HasMany {
        return $this->hasMany(BackupGeneration::class, 'connection_id');
    }

    /** @return BelongsTo<User, $this> */
    public function creator(): BelongsTo {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function isRunnable(): bool {
        return $this->status->isRunnable() && $this->getAttribute('disabled_at') === null;
    }
}
