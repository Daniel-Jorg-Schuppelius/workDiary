<?php
/*
 * Created on   : Sun Oct 04 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ResolvesIsmsScope.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Http\Controllers\Isms\Concerns;

use App\Models\Isms\IsmsScope;
use App\Support\Sqid;
use Illuminate\Support\Collection;

/**
 * Geltungsbereich aus der Anfrage auflösen (Konsolidierungs-Audit 2026-10,
 * k3-6: neun Kopien in vier Bedeutungen). Drei Bedeutungen bleiben, jede mit
 * eigenem Namen:
 *
 * - {@see scopeOrNull()} — Formular oder Filter: nur der gewählte Bereich.
 * - {@see scopeOrDefault()} — Schreibvorgang: gewählt, sonst der Standardbereich.
 * - {@see scopeForView()} — Übersicht: gewählt, sonst Standard, sonst der erste.
 *   Ohne Standardbereich zeigten Dashboard, Readiness und CSF den ersten
 *   Bereich, Anforderungen, Konformität und SoA dagegen nichts.
 */
trait ResolvesIsmsScope {
    /** @param  Collection<int, IsmsScope>|null  $scopes  bereits geladene Bereiche; sonst wird gesucht */
    private function scopeOrNull(mixed $sqid, ?Collection $scopes = null): ?IsmsScope {
        if (! is_string($sqid) || $sqid === '') {
            return null;
        }
        $id = Sqid::decode(IsmsScope::class, $sqid);
        if ($id === null) {
            return null;
        }

        return $scopes !== null ? $scopes->firstWhere('id', $id) : IsmsScope::query()->whereKey($id)->first();
    }

    /** @param  Collection<int, IsmsScope>|null  $scopes */
    private function scopeOrDefault(mixed $sqid, ?Collection $scopes = null): ?IsmsScope {
        return $this->scopeOrNull($sqid, $scopes)
            ?? ($scopes !== null ? $scopes->firstWhere('is_default', true) : IsmsScope::query()->where('is_default', true)->first());
    }

    /** @param  Collection<int, IsmsScope>|null  $scopes */
    private function scopeForView(mixed $sqid, ?Collection $scopes = null): ?IsmsScope {
        return $this->scopeOrDefault($sqid, $scopes)
            ?? ($scopes !== null ? $scopes->first() : IsmsScope::query()->orderBy('name')->first());
    }
}
