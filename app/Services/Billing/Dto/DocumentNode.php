<?php
/*
 * Created on   : Tue Oct 13 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : DocumentNode.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Billing\Dto;

use Carbon\CarbonInterface;

/** Ein Beleg im Belegverbund, so wie die Anzeige ihn braucht (MVP-1157). */
final readonly class DocumentNode {
    /**
     * @param  string  $label  Belegart, übersetzt
     * @param  ?string  $state  Status, übersetzt
     * @param  ?string  $system  Programmname; null = workDiary
     * @param  ?string  $url  nur, wenn der Nutzer den Beleg öffnen darf
     * @param  bool  $dialog  Link öffnet einen Dialog (Vorschau eines Programmbelegs)
     */
    public function __construct(
        public DocumentRef $ref,
        public string $label,
        public ?string $number,
        public ?CarbonInterface $date = null,
        public ?string $state = null,
        public ?string $system = null,
        public ?string $url = null,
        public bool $dialog = false,
    ) {}
}
