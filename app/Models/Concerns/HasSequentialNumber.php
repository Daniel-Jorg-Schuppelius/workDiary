<?php
/*
 * Created on   : Fri Jul 17 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : HasSequentialNumber.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Models\Concerns;

use App\Enums\Numbering\NumberScope;
use App\Services\Numbering\{NumberAuthority, NumberSequenceService};

/**
 * Laufende Nummernvergabe über den zentralen NumberSequenceService
 * (konsolidierungs-audit-2026-07, Befund D2). Erwartet die Spalten
 * `number`, `number_source` und `organization_id`; das Model liefert
 * Nummernkreis und Fallback über die beiden abstrakten Hooks.
 */
trait HasSequentialNumber {
    /** Nummernkreis des Models (z. B. NumberScope::Customer). */
    abstract protected static function numberScope(): NumberScope;

    /** Deterministische Dummy-Nummer ohne Org-Kontext (nur Test-Setup). */
    abstract protected static function numberFallback(): string;

    /**
     * Aus booted() aufrufen — bewusst kein boot{Trait}-Hook, damit die
     * Nummernvergabe erst NACH allen Trait-Boots registriert wird
     * (organization_id muss bereits gesetzt sein).
     */
    protected static function registerSequentialNumberHook(): void {
        static::creating(function (self $model): void {
            if ($model->number === null || $model->number === '') {
                $external = app(NumberAuthority::class)->isExternal($model->organization_id, static::numberScope());
                $model->number = self::nextNumberFor($model->organization_id);
                if ((string) $model->number_source === '') {
                    $model->number_source = $external ? 'lexoffice' : 'local';
                }
            }
        });
    }

    /**
     * Berechnet die nächste freie Nummer für die Organisation über den
     * zentralen {@see NumberSequenceService} (inkl. Kollisions-Retry gegen
     * bereits vergebene Nummern, z. B. aus externen Systemen).
     */
    public static function nextNumberFor(?int $organizationId): string {
        if ($organizationId === null) {
            // Greenfield-Fallback ohne Org-Kontext (nur Test-Setup): deterministische Dummy-Nummer.
            return static::numberFallback();
        }

        /** @var NumberSequenceService $service */
        $service = app(NumberSequenceService::class);

        // TENANT-BYPASS: ohne Global Scope, weil $organizationId explizit übergeben wird;
        // der explizite where('organization_id', ...) erhält die Mandantengrenze.
        $maxAttempts = static::query()
            ->withoutGlobalScopes()
            ->where('organization_id', $organizationId)
            ->count() + 1;

        for ($attempt = 0; $attempt < $maxAttempts; $attempt++) {
            $number = $service->next($organizationId, static::numberScope());

            if (! static::query()
                ->withoutGlobalScopes()
                ->where('organization_id', $organizationId)
                ->where('number', $number)
                ->exists()) {
                return $number;
            }
        }

        throw new \RuntimeException(sprintf('No free %s number could be generated.', static::numberScope()->value));
    }
}
