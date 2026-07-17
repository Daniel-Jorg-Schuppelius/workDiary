<?php
/*
 * Created on   : Fri Jul 17 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : AppendOnly.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Models\Concerns;

use RuntimeException;

/**
 * Macht ein Modell append-only: UPDATE und DELETE werfen auf Modell-Ebene.
 * Korrekturen erfolgen fachlich (Gegenbuchung, versioniertes Folge-Ereignis),
 * nie durch Änderung der Originalzeile.
 *
 * Greift nur über Eloquent-Model-Events — Bulk-/Quiet-/Raw-Writes überwacht
 * das Architektur-Gate {@see \Tests\Unit\Architecture\GobdLockGuardRuleTest}.
 *
 * Modelle mit legitimer Löschung (Retention/Pruning) überschreiben
 * {@see appendOnlyAllowsDelete()} mit true.
 *
 * @method static void updating(\Closure $callback)
 * @method static void deleting(\Closure $callback)
 */
trait AppendOnly {
    public static function bootAppendOnly(): void {
        static::updating(function (): void {
            throw new RuntimeException(static::class . ' ist append-only und darf nicht geändert werden.');
        });
        static::deleting(function (): void {
            if (static::appendOnlyAllowsDelete()) {
                return;
            }

            throw new RuntimeException(static::class . ' ist append-only und darf nicht gelöscht werden.');
        });
    }

    /**
     * Delete-Ausnahme (Retention/Pruning) — Standard: Löschen verboten.
     */
    protected static function appendOnlyAllowsDelete(): bool {
        return false;
    }
}
