<?php
/*
 * Created on   : Wed Aug 19 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : MigrationDataArea.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Enums\Migration;

use App\Enums\Concerns\HasOptions;
use App\Enums\Contracts\HasLabel;

/**
 * Datenbereiche eines Buchhaltungswechsels (MVP-653). Je Bereich werden
 * Mengen, Zuordnungen und Konflikte getrennt geführt; Belege sind
 * ausdrücklich read-only Historie und werden nie neu erzeugt.
 */
enum MigrationDataArea: string implements HasLabel {
    use HasOptions;

    case Customers = 'customers';
    case Suppliers = 'suppliers';
    case Articles = 'articles';
    case Documents = 'documents';

    public function label(): string {
        return match ($this) {
            self::Customers => __('Kunden'),
            self::Suppliers => __('Lieferanten'),
            self::Articles => __('Artikel und Leistungen'),
            self::Documents => __('Belege (Historie)'),
        };
    }

    /**
     * Wird der Bereich im Zielsystem aufgebaut (Stammdaten) oder bleibt er
     * reine Lesehistorie? Belege werden nie nachgebaut (Grundsatz des Issues).
     */
    public function isBuildable(): bool {
        return $this !== self::Documents;
    }

    /** Zugehörige orgaMAX-Capability (Datenführerschaft je Bereich). */
    public function capability(): string {
        return match ($this) {
            self::Customers => 'customers',
            self::Suppliers => 'suppliers',
            self::Articles => 'articles',
            self::Documents => 'documents',
        };
    }
}
