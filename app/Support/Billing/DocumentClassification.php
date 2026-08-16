<?php
/*
 * Created on   : Sun Aug 16 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : DocumentClassification.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Support\Billing;

use App\Enums\Billing\{DocumentDirection, DocumentKind, DocumentOrigin};

/**
 * Vier-Achsen-Einordnung einer Belegzeile im Belegfluss (Feature 105).
 */
final readonly class DocumentClassification {
    public function __construct(
        public DocumentOrigin $origin,
        public DocumentDirection $direction,
        public DocumentKind $kind,
    ) {}

    /**
     * Vorzeichen für Geldsummen: 0 bei Vorgängen ohne Geldwirkung
     * (Angebot, Auftragsbestätigung, Lieferschein).
     */
    public function sign(): int {
        return $this->direction->isMonetary() ? $this->kind->sign() : 0;
    }
}
