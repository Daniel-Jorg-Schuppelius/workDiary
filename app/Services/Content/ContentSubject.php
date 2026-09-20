<?php
/*
 * Created on   : Sat Sep 20 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ContentSubject.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Content;

use App\Models\{Customer, ForeignCustomer, Project};
use Illuminate\Database\Eloquent\Model;

/**
 * Die Zugehörigkeit eines Inhalts (MVP-818): Kunde, ggf. Fremdkunde, Projekt
 * und der unmittelbare Träger — für ein Dokument am Auftrag also
 * „Müller GmbH › Dachsanierung › Auftrag Heizungstausch".
 *
 * Aufgelöst wird sie von {@see ContentSubjectResolver}; leer bleibt sie für
 * Inhalte, die keinem Vorgang gehören (ein Wissensartikel etwa gehört keinem
 * Kunden — die leere Kette ist dort die Aussage).
 */
final readonly class ContentSubject {
    public function __construct(
        public ?Customer $customer = null,
        public ?ForeignCustomer $foreignCustomer = null,
        public ?Project $project = null,
        public ?Model $carrier = null,
    ) {}

    public function isEmpty(): bool {
        return $this->chain() === [];
    }

    /**
     * Die Kette vom Groben zum Feinen, ohne Lücken.
     *
     * @return list<Model>
     */
    public function chain(): array {
        return array_values(array_filter(
            [$this->customer, $this->foreignCustomer, $this->project, $this->carrier],
            static fn (?Model $model): bool => $model !== null,
        ));
    }
}
