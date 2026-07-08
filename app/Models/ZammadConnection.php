<?php
/*
 * Created on   : Sat Jul 05 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ZammadConnection.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Models;

use App\Models\Concerns\{Auditable, BelongsToOrganization};
use Illuminate\Database\Eloquent\Factories\{Factory, HasFactory};
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Zammad-Anbindung einer Organisation (Feature 060, MVP-129). Der API-Token
 * ist at-rest verschlüsselt (`encrypted`-Cast, APP_KEY). `queue_map` ordnet
 * Zammad-Gruppen (Queues) WorkDiary-Projekten zu; ohne Treffer greift
 * `default_project_id` (oder die Aufgabe wird global).
 *
 * @property int $id
 * @property int $organization_id
 * @property string $name
 * @property string $base_url
 * @property string $api_token
 * @property string|null $webhook_secret
 * @property bool $active
 * @property int|null $default_project_id
 * @property array<int|string, int>|null $queue_map
 * @property string|null $resolved_state
 * @property string|null $time_unit
 * @property \Illuminate\Support\Carbon|null $last_polled_at
 */
class ZammadConnection extends Model {
    use Auditable;
    use BelongsToOrganization;
    /** @use HasFactory<Factory<static>> */
    use HasFactory;

    /** Geheimnisse nie serialisieren/auditieren. */
    protected $hidden = [
        'api_token',
        'webhook_secret',
    ];

    protected $fillable = [
        'organization_id',
        'name',
        'base_url',
        'api_token',
        'webhook_secret',
        'active',
        'default_project_id',
        'queue_map',
        'resolved_state',
        'time_unit',
        'ticket_target',
        'service_queue_id',
        'last_polled_at',
        'created_by',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'api_token' => 'encrypted',
        'webhook_secret' => 'encrypted',
        'active' => 'boolean',
        'queue_map' => 'array',
        'last_polled_at' => 'datetime',
    ];

    /** Betriebsbereit: aktiv geschaltet und mit URL + Token versehen. */
    public function isActive(): bool {
        return $this->active && $this->base_url !== '' && $this->api_token !== '';
    }

    /**
     * Status-Rückkanal aktiv? Nur wenn ein Zielstatus konfiguriert ist, meldet
     * eine erledigte Aufgabe das verknüpfte Ticket zurück (opt-in, 2. Stufe).
     */
    public function pushesResolution(): bool {
        return $this->isActive() && is_string($this->resolved_state) && $this->resolved_state !== '';
    }

    /**
     * Zeit-Rückkanal aktiv? Nur wenn eine Zeiteinheiten-Konvention konfiguriert
     * ist, werden erfasste Zeiten ins verknüpfte Ticket zurückgebucht (opt-in,
     * Rang 23).
     */
    public function pushesTime(): bool {
        return $this->isActive() && in_array($this->time_unit, ['minute', 'hour'], true);
    }

    /** @return BelongsTo<Project, $this> */
    public function defaultProject(): BelongsTo {
        return $this->belongsTo(Project::class, 'default_project_id');
    }
}
