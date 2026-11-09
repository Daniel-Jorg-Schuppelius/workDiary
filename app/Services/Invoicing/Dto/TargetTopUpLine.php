<?php
/*
 * Created on   : Sun Nov 08 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : TargetTopUpLine.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Invoicing\Dto;

use App\Models\Project\ProjectTimeShortfall;
use App\Services\Invoicing\{BlockPrice, ResolvedService};

/** Bepreiste Auffüllung eines Tagesbefunds auf das Projekt-Soll (Feature 194), gemeinsam für Übergabe und Rechnung. */
final class TargetTopUpLine {
    public function __construct(
        public readonly ProjectTimeShortfall $shortfall,
        public readonly int $minutes,
        public readonly float $hours,
        public readonly BlockPrice $price,
        public readonly ?ResolvedService $service,
        /** „Vereinbarte Einsatzzeit — Person, Datum“ */
        public readonly string $name,
        /** „Soll … h, gebucht … h — Auffüllung … h“ */
        public readonly string $detail,
    ) {}
}
