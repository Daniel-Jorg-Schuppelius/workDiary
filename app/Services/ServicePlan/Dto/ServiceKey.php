<?php
/*
 * Created on   : Thu Nov 12 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ServiceKey.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\ServicePlan\Dto;

use App\Models\Classification\EntryType;
use App\Support\Sqid;
use Stringable;

/**
 * Eine Leistung des Leistungsplans (Feature 199): Eintragsart + Produktgruppe
 * (Code der Klassifikation `product_group`, null = jede Produktgruppe der
 * Eintragsart) — dieselbe Zuordnung wie im Leistungsumfang `customer_services`
 * (Feature 197). Intern als `<id>:<code>`, in Adressen mit der Sqid der
 * Eintragsart.
 */
final readonly class ServiceKey implements Stringable {
    public ?string $productGroup;

    public function __construct(public int $entryTypeId, ?string $productGroup = null) {
        $productGroup = $productGroup !== null ? trim($productGroup) : null;
        $this->productGroup = $productGroup !== '' ? $productGroup : null;
    }

    /** Interner Schlüssel (Arrays, Vergleiche). */
    public function key(): string {
        return $this->entryTypeId . ':' . ($this->productGroup ?? '');
    }

    public function __toString(): string {
        return $this->key();
    }

    /** Schlüssel für Adressen und Formulare (Sqid der Eintragsart). */
    public function param(): string {
        return Sqid::encode(EntryType::class, $this->entryTypeId) . ':' . ($this->productGroup ?? '');
    }

    /** Liest {@see param()}; null bei fremder oder ungültiger Sqid. */
    public static function fromParam(mixed $value): ?self {
        if (! is_string($value) || $value === '') {
            return null;
        }
        $separator = strpos($value, ':');
        $sqid = $separator === false ? $value : substr($value, 0, $separator);
        $code = $separator === false ? null : substr($value, $separator + 1);
        $id = Sqid::decode(EntryType::class, $sqid);
        if ($id === null || ($code !== null && preg_match('/^[A-Za-z0-9_.\-]{0,64}$/', $code) !== 1)) {
            return null;
        }

        return new self($id, $code);
    }

    /** Liest {@see key()}; null bei ungültigem Wert. */
    public static function fromKey(string $value): ?self {
        if (preg_match('/^(\d+):([A-Za-z0-9_.\-]{0,64})$/', $value, $match) !== 1) {
            return null;
        }

        return new self((int) $match[1], $match[2]);
    }

    public function equals(self $other): bool {
        return $this->key() === $other->key();
    }

    /**
     * Gehört ein Auftrag dieser Eintragsart und Produktgruppe zur Leistung?
     * Ohne Produktgruppe an der Leistung zählt jede Produktgruppe.
     */
    public function covers(int $entryTypeId, ?string $productGroup): bool {
        return $entryTypeId === $this->entryTypeId
            && ($this->productGroup === null || $this->productGroup === $productGroup);
    }
}
