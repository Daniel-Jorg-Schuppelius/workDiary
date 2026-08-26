<?php
/*
 * Created on   : Tue Aug 25 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : PickList.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Inventory;

use App\Support\EntityType;
use Illuminate\Database\Eloquent\Model;

/**
 * Ergebnis des {@see PickListBuilder} (Feature 048, MVP-706): sortierte
 * Positionen plus optionale fachliche Quelle (z. B. Fertigungsauftrag).
 */
final class PickList {
    /** @param list<PickListLine> $lines */
    public function __construct(
        public readonly array $lines,
        public readonly ?Model $source = null,
    ) {}

    public function isEmpty(): bool {
        return $this->lines === [];
    }

    /** Anzeige der Quelle: Typ-Label + Nummer/Bezeichnung (nie roher Morph-Name). */
    public function sourceLabel(): ?string {
        if ($this->source === null) {
            return null;
        }
        $type = EntityType::label($this->source::class);
        $number = $this->source->getAttribute('number') ?? $this->source->getAttribute('name');

        return $number !== null && $number !== '' ? $type . ' ' . $number : $type;
    }

    /** Stabile Belegnummer für Dateiname/Kopf (aus Quelle abgeleitet). */
    public function number(): string {
        $suffix = $this->source !== null
            ? str_pad((string) $this->source->getKey(), 6, '0', STR_PAD_LEFT)
            : now()->format('YmdHi');

        return 'KL-' . $suffix;
    }
}
