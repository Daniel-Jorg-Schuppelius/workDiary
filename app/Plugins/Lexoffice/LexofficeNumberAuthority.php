<?php
/*
 * Created on   : Mon Jun 01 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : LexofficeNumberAuthority.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Plugins\Lexoffice;

use App\Enums\Numbering\NumberScope;
use App\Models\Organization;
use App\Services\Numbering\{NumberAuthority, NumberSequenceService};

/**
 * Überträgt die Lexoffice-Plugin-Einstellung „Nummernkreise von Lexoffice
 * führen lassen" auf die buchhaltungsrelevanten Nummernkreise, indem
 * `number_formats.source` entsprechend gesetzt wird.
 *
 * Hält den Core entkoppelt: {@see NumberAuthority} liest ausschließlich
 * `number_formats.source`; dieses Plugin-Detail schreibt den Flag.
 */
class LexofficeNumberAuthority {
    public function __construct(
        private readonly NumberSequenceService $numbers = new NumberSequenceService(),
    ) {}

    /**
     * Setzt für alle buchhaltungsrelevanten Scopes die Hoheit der Organisation.
     */
    public function apply(Organization $organization, bool $external): void {
        $source = $external ? NumberAuthority::SOURCE_EXTERNAL : NumberAuthority::SOURCE_LOCAL;

        foreach (NumberScope::cases() as $scope) {
            if (! $scope->isAccountingRelevant()) {
                continue;
            }
            $this->numbers->setFormat($organization, $scope, ['source' => $source]);
        }
    }
}
