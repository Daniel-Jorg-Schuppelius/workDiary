<?php
/*
 * Created on   : Sun Aug 16 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : DocumentFeedFilters.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Services\Billing;

use App\Enums\Billing\{DocumentDirection, DocumentKind, DocumentOrigin};
use Carbon\CarbonImmutable;

/**
 * Filterzustand des Belegflusses (Feature 105, MVP-543).
 *
 * Enthält bewusst auch den Sichtbarkeits-Kontext (Nutzer, Adminrecht,
 * Auslagen-Umfang): Liste und Kennzahlenband müssen zwingend denselben
 * Ausschnitt sehen — sonst zeigt eine Kachel Beträge, die die Zeilen darunter
 * nicht belegen.
 */
final readonly class DocumentFeedFilters {
    /**
     * @param  list<DocumentKind>  $kinds
     * @param  list<DocumentDirection>  $directions
     */
    public function __construct(
        public int $organizationId,
        public int $userId,
        public CarbonImmutable $from,
        public CarbonImmutable $to,
        public array $kinds = [],
        public array $directions = [],
        public ?DocumentOrigin $origin = null,
        public ?string $contactType = null,
        public ?int $customerId = null,
        public ?string $state = null,
        public string $search = '',
        public bool $onlyOverdue = false,
        public bool $includeArchived = false,
        public bool $allExpenses = false,
        public bool $onlyUnlinkedExpenses = false,
        /**
         * Sichtbarkeit je Quelle — der Feed zeigt nur, was die jeweilige
         * Policy erlaubt, und das Kennzahlenband rechnet mit derselben Menge.
         *
         * @var array<string, bool>
         */
        public array $sources = [],
    ) {}

    /** Darf diese Quelle abgefragt werden? Unbekannte Quellen bleiben außen vor. */
    public function allows(string $source): bool {
        return (bool) ($this->sources[$source] ?? false);
    }

    /**
     * Werte der gewünschten Vorgangsarten (leer = alle).
     *
     * @return list<string>
     */
    public function kindValues(): array {
        return array_map(static fn(DocumentKind $k): string => $k->value, $this->kinds);
    }

    /**
     * Werte der gewünschten Richtungen (leer = alle).
     *
     * @return list<string>
     */
    public function directionValues(): array {
        return array_map(static fn(DocumentDirection $d): string => $d->value, $this->directions);
    }

    /** Prüft, ob eine Quelle mit dieser Herkunft überhaupt abgefragt werden muss. */
    public function wantsOrigin(DocumentOrigin $origin): bool {
        return $this->origin === null || $this->origin === $origin;
    }

    /**
     * Prüft, ob eine Quelle mit fester Richtung/Art abgefragt werden muss —
     * spart ganze Sub-Selects, wenn der Tab sie ohnehin ausschließt.
     */
    public function wantsFixed(DocumentDirection $direction, ?DocumentKind $kind = null): bool {
        if ($this->directions !== [] && ! in_array($direction, $this->directions, true)) {
            return false;
        }

        return $kind === null || $this->kinds === [] || in_array($kind, $this->kinds, true);
    }
}
