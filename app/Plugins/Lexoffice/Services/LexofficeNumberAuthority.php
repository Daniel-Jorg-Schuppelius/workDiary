<?php
/*
 * Created on   : Mon Jun 01 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : LexofficeNumberAuthority.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Plugins\Lexoffice\Services;

use App\Enums\Numbering\NumberScope;
use App\Models\Platform\Organization;
use App\Plugins\Lexoffice\LexofficePlugin;
use App\Services\Numbering\{NumberAuthority, NumberSequenceService};

/**
 * Überträgt den Lexoffice-Schalter „Rechnungsnummern von Lexoffice führen
 * lassen“ auf die Belegkreise (`number_formats.source`). Kunden- und
 * Lieferantennummern wählt die Organisation auf der Seite „Nummernkreise“
 * (MVP-1130); der Schalter fasst sie nicht an.
 */
class LexofficeNumberAuthority {
    /** Belegkreise, die der Schalter steuert. */
    public const SCOPES = [NumberScope::Invoice, NumberScope::CreditNote, NumberScope::Cancellation];

    public function __construct(
        private readonly NumberSequenceService $numbers = new NumberSequenceService(),
        private readonly NumberAuthority $authority = new NumberAuthority(),
    ) {}

    /** Ausschalten setzt nur Kreise zurück, die Lexoffice vergibt. */
    public function apply(Organization|int $organization, bool $external): void {
        foreach (self::SCOPES as $scope) {
            $current = $this->authority->configuredSource($organization, $scope);
            if ($external) {
                $this->numbers->setFormat($organization, $scope, ['source' => LexofficePlugin::ID]);
            } elseif ($current === LexofficePlugin::ID) {
                $this->numbers->setFormat($organization, $scope, ['source' => NumberAuthority::SOURCE_LOCAL]);
            }
        }
    }
}
