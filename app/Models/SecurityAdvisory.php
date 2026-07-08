<?php
/*
 * Created on   : Tue Jul 07 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : SecurityAdvisory.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\HasSqid;
use Illuminate\Database\Eloquent\{Builder, Model};

/**
 * OSV-Sicherheitshinweis für eine installierte Abhängigkeit (Rang 70).
 * Installationsweit (kein Org-Scope); gepflegt durch `security:advisories-pull`.
 *
 * @property int $id
 * @property string $source
 * @property string $external_id
 * @property string $ecosystem
 * @property string $package
 * @property string $installed_version
 * @property string $severity
 * @property string|null $cvss_vector
 * @property string|null $summary
 * @property string|null $fixed_in
 * @property string|null $statement
 * @property \Illuminate\Support\Carbon|null $modified_at
 * @property \Illuminate\Support\Carbon|null $resolved_at
 */
class SecurityAdvisory extends Model {
    use HasSqid;

    /** Schweregrade in absteigender Ordnung (für Sortierung/Gate). */
    public const SEVERITIES = ['critical', 'high', 'medium', 'low', 'unknown'];

    protected $fillable = [
        'source',
        'external_id',
        'ecosystem',
        'package',
        'installed_version',
        'severity',
        'cvss_vector',
        'summary',
        'fixed_in',
        'statement',
        'modified_at',
        'resolved_at',
    ];

    protected function casts(): array {
        return [
            'modified_at' => 'datetime',
            'resolved_at' => 'datetime',
        ];
    }

    /** @param Builder<self> $query */
    public function scopeOpen(Builder $query): void {
        $query->whereNull('resolved_at');
    }

    /** Offene Advisories mit Schweregrad high/critical (Gate-Kriterium). */
    public static function openHighOrCritical(): int {
        return (int) self::query()->whereNull('resolved_at')
            ->whereIn('severity', ['critical', 'high'])
            ->count();
    }
}
