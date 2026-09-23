<?php
/*
 * Created on   : Wed Jul 08 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ComponentUpdate.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;

/**
 * Bekanntes verfügbares Update einer Komponente (MVP-054). Genau eine
 * Zeile je Komponente (unique) — neue Versionen aktualisieren die Zeile.
 * Snooze/Acknowledge sind auditiert (Abschaltungen nachvollziehbar,
 * DoD 022); Security-/Critical-Einstufungen bleiben auf der
 * Komponenten-/Diagnoseseite immer sichtbar.
 *
 * @property int $id
 * @property string $component_type
 * @property string $component_key
 * @property string|null $installed_version
 * @property string $available_version
 * @property string $channel
 * @property string $classification
 * @property bool $compatible
 * @property string|null $changelog_url
 * @property array<string, mixed>|null $requires
 * @property string $source
 * @property \Carbon\CarbonImmutable $checked_at
 * @property \Carbon\CarbonImmutable|null $acknowledged_at
 * @property \Carbon\CarbonImmutable|null $snoozed_until
 */
class ComponentUpdate extends Model {
    use Auditable;

    public const CLASSIFICATIONS = ['normal', 'recommended', 'security', 'critical'];

    protected $table = 'component_updates';

    protected $fillable = [
        'component_type',
        'component_key',
        'installed_version',
        'available_version',
        'channel',
        'classification',
        'min_app_version',
        'max_app_version',
        'compatible',
        'changelog_url',
        'requires',
        'source',
        'checked_at',
        'acknowledged_at',
        'acknowledged_by',
        'snoozed_until',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'compatible' => 'boolean',
        'requires' => 'array',
        'checked_at' => 'immutable_datetime',
        'acknowledged_at' => 'immutable_datetime',
        'snoozed_until' => 'immutable_datetime',
    ];

    public function isSecurityRelevant(): bool {
        return in_array($this->classification, ['security', 'critical'], true);
    }

    public function isMuted(): bool {
        return $this->acknowledged_at !== null
            || ($this->snoozed_until !== null && $this->snoozed_until->isFuture());
    }
}
